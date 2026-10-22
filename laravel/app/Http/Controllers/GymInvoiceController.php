<?php

namespace App\Http\Controllers;

use App\Models\MembershipOrder;
use App\Services\GymCommerceService;
use App\Services\GymIdentityService;
use App\Services\GymInvoiceDocumentService;
use App\Services\GymPortalService;
use App\Services\OriginalGymSql;
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
        $data = $request->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:Chưa thanh toán,Đã thanh toán,Hủy', 'mode' => 'nullable|in:pickup,delivery']);
        $query = DB::table('HOADON as h')->join('KHACHHANG as k', 'k.MAKH', '=', 'h.MAKH')->leftJoin('gym_fulfillments as f', 'f.invoice_id', '=', 'h.MAHD')->select('h.*', 'k.TENKH', 'f.mode', 'f.state', 'f.cancellation_requested');
        if ($request->user()->role === 'customer') {
            $query->where('h.MAKH', $this->identities->customer($request->user()));
        } else {
            abort_unless($request->user()->canManageOrders(), 403);
        }
        if (! empty($data['q'])) {
            $query->where(function ($query) use ($data): void {
                $query->where('k.TENKH', 'like', '%'.$data['q'].'%')->orWhere('h.MAHD', $data['q']);
            });
        }
        $query->when($data['status'] ?? null, fn ($q, $status) => $q->where('h.TRANGTHAI', $status))->when($data['mode'] ?? null, fn ($q, $mode) => $q->where('f.mode', $mode));

        $legacy = MembershipOrder::query()->whereNull('sql_invoice_id')->where('status', 'pending')->when($request->user()->role === 'customer', fn ($q) => $q->where('user_id', $request->user()->id))->with('gymPackage')->get();

        return view('gym.invoices', ['invoices' => $query->orderByDesc('h.NGAYLAP')->orderByDesc('h.MAHD')->paginate(25)->withQueryString(), 'legacyOrders' => $legacy]);
    }

    public function show(Request $request, int $invoice): View
    {
        $bill = $this->shop->invoice($invoice, $request->user());
        $sets = $this->sql->call('sp_LayChiTietHoaDon', [$invoice]);
        $lines = DB::table('gym_invoice_lines')->where('invoice_id', $invoice)->get();

        return view('gym.invoice', ['invoice' => $bill, 'lines' => $lines, 'settings' => app(GymPortalService::class)->settings(), 'needsReconciliation' => app(GymPortalService::class)->unreconciledInvoices()->where('h.MAHD', $invoice)->exists(), 'originalLines' => $sets[1] ?? [], 'fulfillment' => DB::table('gym_fulfillments')->where('invoice_id', $invoice)->first(), 'receipts' => DB::table('gym_receipts as r')->join('users as u', 'u.id', '=', 'r.user_id')->where('invoice_id', $invoice)->select('r.*', 'u.name')->orderBy('r.id')->get(), 'onlinePayments' => DB::table('gym_online_payments')->where('target_type', 'invoice')->where('target_id', $invoice)->where('user_id', $request->user()->id)->orderByDesc('id')->get(), 'received' => $this->shop->received($invoice)]);
    }

    public function receive(Request $request, int $invoice): RedirectResponse
    {
        $data = $request->validate(['amount' => 'required|integer|min:1|max:1000000000000000', 'method' => 'required|in:cash,bank_transfer', 'reference' => 'nullable|string|max:100', 'token' => 'required|uuid']);
        $this->shop->receive($invoice, $request->user(), $data);

        return back()->with('success', 'Đã ghi nhận khoản thu.');
    }

    public function confirm(Request $request, int $invoice): RedirectResponse
    {
        $data = $request->validate(['start_date' => 'nullable|date|after_or_equal:today']);
        $this->shop->confirm($invoice, $request->user(), $data['start_date'] ?? null);

        return back()->with('success', 'Đã xác nhận hóa đơn và kích hoạt dịch vụ.');
    }

    public function cancel(Request $request, int $invoice): RedirectResponse
    {
        $this->shop->cancel($invoice, $request->user());

        return back()->with('success', $request->user()->role === 'customer' ? 'Đã gửi yêu cầu hủy cho nhân viên.' : 'Đã hủy hóa đơn và giải phóng hàng đang giữ.');
    }

    public function fulfill(Request $request, int $invoice): RedirectResponse
    {
        $data = $request->validate(['state' => 'required|in:ready,collected,shipping,delivered']);
        $this->shop->fulfill($invoice, $request->user(), $data['state']);

        return back()->with('success', 'Đã cập nhật giao nhận.');
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
