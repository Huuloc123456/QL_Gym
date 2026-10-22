<?php

namespace App\Http\Controllers;

use App\Models\GymSetting;
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
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'required_without:gym_name', 'string', 'max:120'],
            'gym_name' => ['nullable', 'required_without:name', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'hours' => ['nullable', 'string', 'max:255'],
            'opening_hours' => ['nullable', 'string', 'max:255'],
            'introduction' => ['nullable', 'string', 'max:3000'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_code' => ['nullable', 'string', 'max:20', 'regex:/^[a-zA-Z0-9]+$/'],
            'bank_account' => ['nullable', 'string', 'max:80'],
            'bank_holder' => ['nullable', 'string', 'max:160'],
            'bank_account_name' => ['nullable', 'string', 'max:160'],
            'faq_items' => ['nullable', 'array', 'max:4'],
            'faq_items.*' => ['array:question,answer'],
            'faq_items.*.question' => ['nullable', 'string', 'max:180', 'required_with:faq_items.*.answer'],
            'faq_items.*.answer' => ['nullable', 'string', 'max:800', 'required_with:faq_items.*.question'],
            'payment_hours' => ['sometimes', 'required', 'integer', 'between:1,168'],
            'reminder_days' => ['sometimes', 'required', 'integer', 'between:1,30'],
            'shipping_fee' => ['sometimes', 'required', 'integer', 'between:0,10000000'],
            'email_enabled' => ['sometimes', 'required', 'boolean'],
        ]);
        $settings = [
            'gym_name' => $data['gym_name'] ?? $data['name'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'opening_hours' => $data['opening_hours'] ?? $data['hours'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'bank_code' => $data['bank_code'] ?? null,
            'bank_account' => $data['bank_account'] ?? null,
            'bank_account_name' => $data['bank_account_name'] ?? $data['bank_holder'] ?? null,
        ];
        foreach (['introduction', 'faq_items', 'payment_hours', 'reminder_days', 'shipping_fee', 'email_enabled'] as $optionalField) {
            if (array_key_exists($optionalField, $data)) {
                $settings[$optionalField] = $optionalField === 'faq_items'
                    ? collect($data[$optionalField] ?? [])
                        ->map(fn (array $item): array => ['question' => trim($item['question'] ?? ''), 'answer' => trim($item['answer'] ?? '')])
                        ->filter(fn (array $item): bool => $item['question'] !== '' && $item['answer'] !== '')
                        ->values()
                        ->all()
                    : $data[$optionalField];
            }
        }
        $this->sql->transaction(function () use ($settings): void {
            GymSetting::current()->fill($settings)->save();
            $this->sql->audit('Cập nhật cấu hình phòng gym', 'gym_settings:1');
        });

        return redirect()->route('manager.settings.edit')->with('success', 'Đã lưu cài đặt. Phí và thời hạn mới chỉ áp dụng cho đơn đặt sau thời điểm này.');
    }

    public function info(Request $request): View
    {
        return view('gym.'.match ($request->route()->getName()) {
            'gym.contact' => 'contact', 'gym.help' => 'help', default => 'policy',
        }, ['settings' => $this->portal->settings()]);
    }
}
