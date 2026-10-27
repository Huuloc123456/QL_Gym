<?php

namespace App\Http\Controllers;

use App\Models\MembershipOrder;
use App\Services\GymCommerceService;
use App\Services\GymIdentityService;
use App\Services\GymInvoiceDocumentService;
use App\Services\GymPortalService;
use App\Services\OriginalGymSql;
use App\Services\VietQrService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GymInvoiceController extends Controller
{
    public function __construct(private GymCommerceService $shop, private GymIdentityService $identities, private GymInvoiceDocumentService $documents, private OriginalGymSql $sql) {}

    public function index(Request $request): View
    {
        $data = $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:Chưa thanh toán,Đã thanh toán,Hủy', 'mode' => 'nullable|in:pickup,delivery', 'source' => 'nullable|in:invoice,package_order']);
        $invoices = DB::table('HOADON as h')
            ->join('KHACHHANG as k', 'k.MAKH', '=', 'h.MAKH')
            ->leftJoin('gym_fulfillments as f', 'f.invoice_id', '=', 'h.MAHD')
            ->selectRaw("'invoice' as source, h.MAHD as record_id, k.TENKH as customer_name, h.NGAYLAP as ordered_at, h.THANHTIEN as amount, h.TRANGTHAI as status, f.mode as mode, f.state as state, f.cancellation_requested as cancellation_requested, (SELECT l.name FROM gym_invoice_lines l WHERE l.invoice_id = h.MAHD ORDER BY l.id LIMIT 1) as item_name");
        $packageOrders = DB::table('membership_orders as o')
            ->join('users as u', 'u.id', '=', 'o.user_id')
            ->join('gym_packages as p', 'p.id', '=', 'o.gym_package_id')
            ->whereNull('o.sql_invoice_id')
            ->selectRaw("'package_order' as source, o.id as record_id, u.name as customer_name, o.created_at as ordered_at, o.amount as amount, CASE o.status WHEN 'paid' THEN 'Đã thanh toán' WHEN 'cancelled' THEN 'Hủy' ELSE 'Chưa thanh toán' END as status, 'pickup' as mode, NULL as state, CASE WHEN o.cancellation_requested_at IS NULL THEN 0 ELSE 1 END as cancellation_requested, p.name as item_name");
        if ($request->user()->role === 'customer') {
            $invoices->where('h.MAKH', $this->identities->customer($request->user()));
            $packageOrders->where('o.user_id', $request->user()->id);
        } else {
            abort_unless($request->user()->canManageOrders(), 403);
        }
        $query = DB::query()->fromSub($invoices->unionAll($packageOrders), 'orders');
        if (! empty($data['q'])) {
            $query->where(function ($query) use ($data): void {
                $query->where('customer_name', 'like', '%'.trim($data['q']).'%')
                    ->orWhere('item_name', 'like', '%'.trim($data['q']).'%');
                if (ctype_digit(trim($data['q']))) {
                    $query->orWhere('record_id', (int) trim($data['q']));
                }
            });
        }
        $query->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($data['mode'] ?? null, fn ($q, $mode) => $q->where('mode', $mode))
            ->when($data['source'] ?? null, fn ($q, $source) => $q->where('source', $source));

        $invoices = $query->orderByDesc('ordered_at')->orderByDesc('record_id')->paginate(25)->withQueryString();

        if ($request->user()->canManageOrders()) {
            $packageOrderIds = $invoices->getCollection()
                ->where('source', 'package_order')
                ->pluck('record_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($packageOrderIds !== []) {
                $packageOrders = MembershipOrder::query()
                    ->with(['user', 'gymPackage', 'membership', 'payments.receiver'])
                    ->whereIn('id', $packageOrderIds)
                    ->get()
                    ->keyBy('id');
                $onlinePayments = DB::table('gym_online_payments')
                    ->where('target_type', 'membership_order')
                    ->whereIn('target_id', $packageOrderIds)
                    ->orderBy('id')
                    ->get()
                    ->groupBy('target_id');

                foreach ($invoices->getCollection() as $row) {
                    if ($row->source !== 'package_order') {
                        continue;
                    }

                    $order = $packageOrders->get((int) $row->record_id);
                    if ($order) {
                        $order->setRelation('onlinePayments', $onlinePayments->get($order->id, collect()));
                    }
                    $row->membershipOrder = $order;
                }
            }
        }

        return view('gym.invoices', compact('invoices'));
    }

    public function show(Request $request, int $invoice): View
    {
        $bill = $this->shop->invoice($invoice, $request->user());
        $sets = $this->sql->call('sp_LayChiTietHoaDon', [$invoice]);
        $lines = DB::table('gym_invoice_lines')->where('invoice_id', $invoice)->get();
        $hasUnsupportedServices = $lines->contains(fn ($line): bool => ! in_array($line->kind, ['product', 'package', 'class'], true));
        $settings = app(GymPortalService::class)->settings();
        $fulfillment = DB::table('gym_fulfillments')->where('invoice_id', $invoice)->first();
        $legacyPackageOrder = $fulfillment ? null : MembershipOrder::query()->where('sql_invoice_id', $invoice)->first();
        $received = $this->shop->received($invoice);
        $needsReconciliation = app(GymPortalService::class)->unreconciledInvoices()->where('h.MAHD', $invoice)->exists();
        $hasPaymentReview = DB::table('gym_online_payments')->where('target_type', 'invoice')->where('target_id', $invoice)->where('status', 'review')->exists();
        $remainingAmount = $bill->TRANGTHAI === 'Hủy' ? 0 : max(0, (int) $bill->THANHTIEN - $received);
        $canShowBankTransferQr = $bill->TRANGTHAI === 'Chưa thanh toán'
            && $fulfillment !== null
            && ! in_array($fulfillment?->state, ['cancelled', 'expired'], true)
            && $remainingAmount > 0
            && ! $hasUnsupportedServices
            && ! $needsReconciliation;
        $canShowBankTransferQr = $canShowBankTransferQr && ! $hasPaymentReview;
        $bankTransferQrUrl = $canShowBankTransferQr
            ? app(VietQrService::class)->invoiceImageUrl($settings, $invoice, $remainingAmount)
            : null;

        return view('gym.invoice', [
            'invoice' => $bill,
            'lines' => $lines,
            'settings' => $settings,
            'needsReconciliation' => $needsReconciliation,
            'hasPaymentReview' => $hasPaymentReview,
            'originalLines' => $sets[1] ?? [],
            'fulfillment' => $fulfillment,
            'legacyPackageOrder' => $legacyPackageOrder,
            'receipts' => DB::table('gym_receipts as r')->join('users as u', 'u.id', '=', 'r.user_id')->where('invoice_id', $invoice)->select('r.*', 'u.name')->orderBy('r.id')->get(),
            'refunds' => DB::table('gym_refunds')->where('target_type', 'invoice')->where('target_id', $invoice)->orderBy('id')->get(),
            'onlinePayments' => DB::table('gym_online_payments')->where('target_type', 'invoice')->where('target_id', $invoice)->orderByDesc('id')->get(),
            'received' => $received,
            'remainingAmount' => $remainingAmount,
            'bankTransferQrUrl' => $bankTransferQrUrl,
            'hasUnsupportedServices' => $hasUnsupportedServices,
        ]);
    }

    public function receive(Request $request, int $invoice): RedirectResponse
    {
        $data = $request->validate(['amount' => 'required|integer|min:1|max:1000000000000000', 'method' => 'required|in:cash,bank_transfer', 'reference' => 'nullable|string|max:100', 'token' => 'required|uuid']);
        $recorded = $this->shop->receive($invoice, $request->user(), $data);

        $message = $recorded
            ? 'Đã ghi nhận khoản thu. Trạng thái hóa đơn mới nhất đã được tải lại.'
            : 'Khoản thu này đã được ghi nhận trước đó; không tạo thêm giao dịch.';

        return redirect()->route('gym.invoices.show', $invoice)->with('success', $message);
    }

    public function confirm(Request $request, int $invoice): RedirectResponse
    {
        $data = $request->validate(['start_date' => 'nullable|date|after_or_equal:today']);
        $confirmed = $this->shop->confirm($invoice, $request->user(), $data['start_date'] ?? null);

        $message = $confirmed
            ? 'Đã xác nhận hóa đơn và kích hoạt dịch vụ.'
            : 'Hóa đơn đã được xác nhận trước đó. Trạng thái mới nhất đã tải lại; không tạo thêm dịch vụ.';

        return redirect()->route('gym.invoices.show', $invoice)->with('success', $message);
    }

    public function cancel(Request $request, int $invoice): RedirectResponse
    {
        $cancelled = $this->shop->cancel($invoice, $request->user());
        $message = $request->user()->role === 'customer'
            ? ($cancelled ? 'Đã gửi yêu cầu hủy cho nhân viên.' : 'Yêu cầu hủy đã được gửi trước đó.')
            : ($cancelled ? 'Đã hủy hóa đơn và giải phóng hàng đang giữ.' : 'Hóa đơn đã được hủy trước đó.');

        return redirect()->route('gym.invoices.show', $invoice)->with('success', $message);
    }

    public function resumeCancellation(Request $request, int $invoice): RedirectResponse
    {
        $resumed = $this->shop->resumeCancellation($invoice, $request->user());

        return redirect()->route('gym.invoices.show', $invoice)->with('success', $resumed
            ? 'Đã xác nhận tiếp tục xử lý đơn theo trao đổi với khách.'
            : 'Yêu cầu hủy đã được xử lý trước đó; không thay đổi thêm.');
    }

    public function fulfill(Request $request, int $invoice): RedirectResponse
    {
        $data = $request->validate(['state' => 'required|in:ready,collected,shipping,delivered']);
        $updated = $this->shop->fulfill($invoice, $request->user(), $data['state']);

        $message = $updated
            ? 'Đã cập nhật giao nhận. Trạng thái hóa đơn mới nhất đã được tải lại.'
            : 'Trạng thái này đã được cập nhật trước đó. Không tạo thêm lịch sử giao nhận.';

        return redirect()->route('gym.invoices.show', $invoice)->with('success', $message);
    }

    public function sign(Request $request, int $invoice): RedirectResponse
    {
        $this->documents->sign($invoice, $request->user());

        return back()->with('success', 'Đã tạo và ký chứng từ bằng RSA-SHA256.');
    }

    public function verify(Request $request, int $invoice): RedirectResponse
    {
        $request->validate(['document' => 'nullable|file|max:2048']);
        $valid = $this->documents->verify($invoice, $request->user(), $request->file('document') ? file_get_contents($request->file('document')->getRealPath()) : null);

        return back()->with('success', $valid ? 'Chữ ký hợp lệ, nội dung không bị thay đổi.' : 'Chữ ký không hợp lệ: nội dung tệp đã thay đổi.');
    }

    public function download(Request $request, int $invoice): StreamedResponse
    {
        $bill = $this->shop->invoice($invoice, $request->user());
        abort_unless($bill->FILE_PATH && str_starts_with($bill->FILE_PATH, 'gym-invoices/') && Storage::disk('local')->exists($bill->FILE_PATH), 404);

        return Storage::disk('local')->download($bill->FILE_PATH, 'hoa-don-'.$invoice.'.html', ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
