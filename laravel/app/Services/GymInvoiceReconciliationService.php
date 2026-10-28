<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GymInvoiceReconciliationService
{
    public const CATEGORIES = [
        'missing_details',
        'detail_sources_disagree',
        'detail_total_mismatch',
        'payable_mismatch',
        'payment_mismatch',
        'package_link_mismatch',
        'legacy_service',
        'other',
    ];

    public const STATUSES = ['in_review', 'blocked', 'resolved'];

    public function __construct(private GymPortalService $portal, private OriginalGymSql $sql) {}

    public function cases(): Collection
    {
        $query = $this->portal->unreconciledInvoices()
            ->leftJoin('gym_invoice_reconciliations as reconciliation', 'reconciliation.invoice_id', '=', 'h.MAHD')
            ->leftJoin('users as owner', 'owner.id', '=', 'reconciliation.owner_user_id')
            ->select([
                'h.MAHD as invoice_id', 'h.MAKH as customer_id', 'h.NGAYLAP as invoice_date',
                'h.TONGTIEN as gross_total', 'h.GIAMGIA as discount_total', 'h.THANHTIEN as payable_total',
                'h.TRANGTHAI as invoice_status', 'reconciliation.category as review_category',
                'reconciliation.status as review_status', 'reconciliation.owner_user_id',
                'owner.name as owner_name', 'reconciliation.evidence', 'reconciliation.note',
            ])
            ->selectSub(DB::table('CHITIETHOADON as d')->selectRaw('COUNT(*)')->whereColumn('d.MAHD', 'h.MAHD'), 'legacy_line_count')
            ->selectSub(DB::table('CHITIETHOADON as d')->selectRaw('COALESCE(SUM(d.SOLUONG * d.DONGIA), 0)')->whereColumn('d.MAHD', 'h.MAHD'), 'legacy_line_total')
            ->selectSub(DB::table('gym_invoice_lines as l')->selectRaw('COUNT(*)')->whereColumn('l.invoice_id', 'h.MAHD'), 'current_line_count')
            ->selectSub(DB::table('gym_invoice_lines as l')->selectRaw('COALESCE(SUM(l.quantity * l.unit_price), 0)')->whereColumn('l.invoice_id', 'h.MAHD'), 'current_line_total')
            ->selectSub(DB::table('gym_receipts as r')->selectRaw('COUNT(*)')->whereColumn('r.invoice_id', 'h.MAHD'), 'receipt_count')
            ->selectSub(DB::table('gym_receipts as r')->selectRaw('COALESCE(SUM(r.amount), 0)')->whereColumn('r.invoice_id', 'h.MAHD'), 'receipt_total')
            ->selectSub(DB::table('gym_online_payments as p')->selectRaw('COUNT(*)')->where('p.target_type', 'invoice')->whereColumn('p.target_id', 'h.MAHD'), 'online_payment_count')
            ->selectSub(DB::table('gym_online_payments as p')->selectRaw('COALESCE(SUM(p.amount), 0)')->where('p.target_type', 'invoice')->whereColumn('p.target_id', 'h.MAHD'), 'online_payment_total')
            ->selectSub(DB::table('gym_online_payments as p')->selectRaw("GROUP_CONCAT(DISTINCT p.status ORDER BY p.status SEPARATOR ', ')")->where('p.target_type', 'invoice')->whereColumn('p.target_id', 'h.MAHD'), 'online_payment_states')
            ->selectSub(DB::table('gym_refunds as refund')->selectRaw('COALESCE(SUM(refund.amount), 0)')->where('refund.target_type', 'invoice')->whereColumn('refund.target_id', 'h.MAHD'), 'refund_total')
            ->selectSub(DB::table('membership_orders as orders')->selectRaw('COUNT(*)')->whereColumn('orders.sql_invoice_id', 'h.MAHD'), 'package_order_count')
            ->orderBy('h.MAHD');

        return $query->get()->each(function (object $case): void {
            $case->suggested_category = $this->suggestedCategory($case);
        });
    }

    public function record(User $manager, int $invoice, array $data): void
    {
        $data = Validator::make($data, [
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'status' => ['required', Rule::in(self::STATUSES)],
            'owner_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'manager')->where('is_active', true))],
            'evidence' => ['required', 'string', 'min:8', 'max:2000'],
            'note' => ['required', 'string', 'min:8', 'max:2000'],
        ])->validate();

        $this->sql->transaction(function () use ($manager, $invoice, $data): void {
            abort_unless($manager->role === 'manager'
                && User::query()->whereKey($manager->id)->where('role', 'manager')->where('is_active', true)->exists(), 403);
            if (! User::query()->whereKey($data['owner_user_id'])->where('role', 'manager')->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['owner_user_id' => 'Người phụ trách phải là quản lý đang hoạt động.']);
            }

            $source = DB::table('HOADON')->where('MAHD', $invoice)->lockForUpdate()->first();
            abort_unless($source, 404);

            $isAnomalous = $this->portal->unreconciledInvoices()->where('h.MAHD', $invoice)->exists();
            if ($data['status'] === 'resolved' && $isAnomalous) {
                throw ValidationException::withMessages(['status' => 'Chưa thể đánh dấu đã xử lý: nguồn dữ liệu hóa đơn vẫn còn sai lệch.']);
            }
            if ($data['status'] !== 'resolved' && ! $isAnomalous) {
                throw ValidationException::withMessages(['status' => 'Hóa đơn không còn sai lệch nguồn; hãy ghi nhận đã xử lý.']);
            }

            $now = now();
            $review = DB::table('gym_invoice_reconciliations')->where('invoice_id', $invoice)->lockForUpdate()->first();
            $values = $data + ['reviewed_by_user_id' => $manager->id, 'reviewed_at' => $now, 'updated_at' => $now];
            if ($review) {
                DB::table('gym_invoice_reconciliations')->where('id', $review->id)->update($values);
            } else {
                DB::table('gym_invoice_reconciliations')->insert($values + ['invoice_id' => $invoice, 'created_at' => $now]);
            }
            $this->sql->audit('Đối soát hóa đơn lịch sử', 'HOADON:'.$invoice, [
                'category' => $data['category'], 'status' => $data['status'],
                'owner_user_id' => (int) $data['owner_user_id'], 'evidence' => $data['evidence'], 'note' => $data['note'],
            ]);
        });
    }

    private function suggestedCategory(object $case): string
    {
        if ((int) $case->legacy_line_count === 0 && (int) $case->current_line_count === 0) {
            return 'missing_details';
        }
        if ((int) $case->legacy_line_count > 0 && (int) $case->current_line_count > 0
            && (int) $case->legacy_line_total !== (int) $case->current_line_total) {
            return 'detail_sources_disagree';
        }
        if (((int) $case->legacy_line_count > 0 && (int) $case->legacy_line_total !== (int) $case->gross_total)
            || ((int) $case->current_line_count > 0 && (int) $case->current_line_total !== (int) $case->gross_total)) {
            return 'detail_total_mismatch';
        }

        return 'payable_mismatch';
    }
}
