<?php

namespace App\Http\Controllers;

use App\Services\GymPortalService;
use App\Services\OriginalGymSql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GymPortalController extends Controller
{
    public function __construct(private GymPortalService $portal, private OriginalGymSql $sql) {}

    public function messages(Request $request): View
    {
        return view('gym.messages', ['messages' => DB::table('gym_messages')->where('user_id', $request->user()->id)->orderByDesc('id')->paginate(20)]);
    }

    public function read(Request $request, int $message): RedirectResponse
    {
        $row = DB::table('gym_messages')->where('id', $message)->where('user_id', $request->user()->id)->first();
        abort_unless($row, 404);
        DB::table('gym_messages')->where('id', $message)->whereNull('read_at')->update(['read_at' => now()]);

        return redirect($row->path);
    }

    public function readAll(Request $request): RedirectResponse
    {
        DB::table('gym_messages')->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Đã đánh dấu các thông báo là đã đọc.');
    }

    public function settings(): View
    {
        $products = DB::table('SANPHAM as p')->select('p.MASP', 'p.TENSP')->selectSub(DB::table('HINHANH')->select('URL')->whereColumn('MASP', 'p.MASP')->orderByDesc('ISMAIN')->orderBy('MAHINH')->limit(1), 'image')->get();

        return view('gym.settings', ['settings' => $this->portal->settings(), 'mailDriver' => config('mail.default'),
            'failedEmails' => DB::table('gym_messages')->where('email_state', 'failed')->count(),
            'incompleteInvoices' => $this->portal->unreconciledInvoices()->select('h.MAHD', 'h.TRANGTHAI')->get(),
            'missingImages' => $products->filter(fn ($product) => OriginalGymSql::image($product->image, (int) $product->MASP) === asset('images/product-placeholder.svg')),
            'currentClasses' => DB::table('LOPHOC')->whereDate('NGAYKETTHUC', '>=', now()->toDateString())->count(),
            'unreadyTrainers' => DB::table('NHANVIEN as n')->join('CHUCVU as c', 'c.MACHUCVU', '=', 'n.MACHUCVU')->leftJoin('gym_pt_rates as r', 'r.employee_id', '=', 'n.MANV')->where('c.TENCHUCVU', 'PT')->where(function ($q): void {
                $q->whereNull('r.price')->orWhere('r.price', '<=', 0)->orWhereNotExists(fn ($specialty) => $specialty->selectRaw('1')->from('NHANVIEN_CHUYENMON as s')->whereColumn('s.MANV', 'n.MANV'));
            })->select('n.MANV', 'n.TENNV')->get(),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'address' => 'nullable|string|max:255', 'phone' => 'nullable|string|max:20', 'email' => 'nullable|email|max:100', 'hours' => 'nullable|string|max:255', 'bank_name' => 'nullable|string|max:100', 'bank_account' => 'nullable|string|max:50', 'bank_holder' => 'nullable|string|max:150', 'payment_hours' => 'required|integer|between:1,168', 'reminder_days' => 'required|integer|between:1,30', 'shipping_fee' => 'required|integer|between:0,10000000', 'email_enabled' => 'required|boolean']);
        $this->sql->transaction(function () use ($data): void {
            DB::table('gym_settings')->where('id', 1)->update($data);
            $this->sql->audit('Cập nhật cấu hình phòng gym', 'gym_settings:1');
        });

        return back()->with('success', 'Đã lưu cấu hình. Phí và thời hạn mới áp dụng cho đơn đặt sau thời điểm này.');
    }

    public function info(Request $request): View
    {
        return view('gym.'.match ($request->route()->getName()) {
            'gym.contact' => 'contact', 'gym.help' => 'help', default => 'policy',
        }, ['settings' => $this->portal->settings()]);
    }
}
