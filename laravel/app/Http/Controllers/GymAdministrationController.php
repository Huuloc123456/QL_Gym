<?php

namespace App\Http\Controllers;

use App\Jobs\SyncRagProductCatalog;
use App\Models\GymProduct;
use App\Models\GymProductCategory;
use App\Models\User;
use App\Services\GymIdentityService;
use App\Services\OriginalGymSql;
use App\Services\RagProductCatalogService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class GymAdministrationController extends Controller
{
    public const RESOURCES = [
        'products' => ['SANPHAM', 'MASP', 'Sản phẩm'], 'classes' => ['LOPHOC', 'MALOP', 'Lớp học'],
        'customers' => ['KHACHHANG', 'MAKH', 'Khách hàng'], 'employees' => ['NHANVIEN', 'MANV', 'Nhân sự và chuyên môn'],
        'positions' => ['CHUCVU', 'MACHUCVU', 'Chức vụ'], 'tiers' => ['LOAIKHACHHANG', 'MALOAIKH', 'Hạng khách hàng'],
        'specialties' => ['CHUYENMON', 'MACM', 'Chuyên môn'], 'categories' => ['LOAISANPHAM', 'MALOAISP', 'Nhóm sản phẩm'],
    ];

    public function __construct(private OriginalGymSql $sql, private GymIdentityService $identities, private RagProductCatalogService $productCatalog) {}

    private function definition(string $resource): array
    {
        abort_unless(isset(self::RESOURCES[$resource]), 404);

        return self::RESOURCES[$resource];
    }

    public function index(Request $request, string $resource): View
    {
        [$table, $key, $title] = $this->definition($resource);
        $request->validate(['q' => 'nullable|string|max:150']);
        $names = ['SANPHAM' => 'TENSP', 'LOPHOC' => 'TENLOP', 'KHACHHANG' => 'TENKH', 'NHANVIEN' => 'TENNV', 'CHUCVU' => 'TENCHUCVU', 'LOAIKHACHHANG' => 'TENLOAI', 'CHUYENMON' => 'TENCHUYENMON', 'LOAISANPHAM' => 'TENLOAISP'];
        $name = $names[$table];
        $query = match ($resource) {
            'products' => GymProduct::query(),
            'categories' => GymProductCategory::query(),
            default => DB::table($table),
        };
        $query->select(array_keys($this->fields($resource)));
        $query->addSelect($key);
        if ($resource === 'customers' && $request->filled('q')) {
            $search = trim((string) $request->query('q'));
            $query->where(function ($customerQuery) use ($search): void {
                $customerQuery->where('TENKH', 'like', '%'.$search.'%')
                    ->orWhere('SDT', 'like', '%'.$search.'%')
                    ->orWhere('EMAIL', 'like', '%'.$search.'%')
                    ->orWhere('TENDANGNHAP', 'like', '%'.$search.'%');
                if (ctype_digit($search)) {
                    $customerQuery->orWhere('MAKH', (int) $search);
                }
            });
        } else {
            $query->when($request->query('q'), fn ($q, $search) => $q->where($name, 'like', '%'.$search.'%'));
        }
        if ($resource === 'products' && $request->routeIs('staff.products.*')) {
            $query->selectSub(DB::table('gym_invoice_lines as reserved_lines')
                ->join('HOADON as reserved_invoices', 'reserved_invoices.MAHD', '=', 'reserved_lines.invoice_id')
                ->selectRaw('COALESCE(SUM(reserved_lines.quantity), 0)')
                ->whereColumn('reserved_lines.item_id', 'SANPHAM.MASP')
                ->where('reserved_lines.kind', 'product')
                ->where('reserved_invoices.TRANGTHAI', 'Chưa thanh toán'), 'reserved_quantity');
        }

        return view('gym.admin.index', ['resource' => $resource, 'title' => $title, 'key' => $key, 'fields' => $this->fields($resource), 'rows' => $query->orderByDesc($key)->paginate(25)->withQueryString(), 'routePrefix' => $request->routeIs('staff.products.*') ? 'staff.products' : 'gym.admin', 'staffMode' => $request->routeIs('staff.products.*')]);
    }

    public function showCustomer(int $customer): View
    {
        $profile = DB::table('KHACHHANG as k')
            ->leftJoin('LOAIKHACHHANG as t', 't.MALOAIKH', '=', 'k.MALOAIKH')
            ->where('k.MAKH', $customer)
            ->select('k.*', 't.TENLOAI', 't.MUCGIAM', 't.REQUIRES_VERIFICATION')
            ->first();
        abort_unless($profile, 404);

        $account = User::query()->where('sql_customer_id', $customer)->first();
        $tierVerification = DB::table('gym_customer_tier_verifications as v')
            ->leftJoin('users as u', 'u.id', '=', 'v.verified_by')
            ->where('v.customer_id', $customer)
            ->where('v.tier_id', $profile->MALOAIKH)
            ->whereNull('v.revoked_at')
            ->orderByDesc('v.id')
            ->select('v.*', 'u.name as verifier_name')
            ->first();
        $memberships = DB::table('DANGKYGOITAP as d')
            ->join('GOITAP as g', 'g.MAGOITAP', '=', 'd.MAGOITAP')
            ->where('d.MAKH', $customer)
            ->select('d.*', 'g.TENGOI')
            ->orderByDesc('d.NGAYBATDAU')
            ->paginate(10, ['*'], 'membership_page');
        $invoices = DB::table('HOADON as h')->where('h.MAKH', $customer)
            ->selectRaw("'invoice' as source, h.MAHD as record_id, h.NGAYLAP as ordered_at, h.THANHTIEN as amount, h.TRANGTHAI as status, COALESCE((SELECT l.name FROM gym_invoice_lines l WHERE l.invoice_id = h.MAHD ORDER BY l.id LIMIT 1), 'Hóa đơn') as item_name");
        $legacyOrders = DB::table('membership_orders as o')
            ->join('gym_packages as p', 'p.id', '=', 'o.gym_package_id')
            ->whereNull('o.sql_invoice_id')
            ->when($account, fn ($query) => $query->where('o.user_id', $account->id), fn ($query) => $query->whereRaw('1 = 0'))
            ->selectRaw("'package_order' as source, o.id as record_id, o.created_at as ordered_at, o.amount as amount, CASE o.status WHEN 'paid' THEN 'Đã thanh toán' WHEN 'cancelled' THEN 'Hủy' ELSE 'Chưa thanh toán' END as status, p.name as item_name");
        $invoices = DB::query()->fromSub($invoices->unionAll($legacyOrders), 'customer_orders')
            ->orderByDesc('ordered_at')->orderByDesc('record_id')->paginate(10, ['*'], 'invoice_page');
        $checkIns = DB::table('CHECKIN')->where('MAKH', $customer)->orderByDesc('THOIGIANVAO')->paginate(10, ['*'], 'checkin_page');

        return view('manager.customers.show', compact('profile', 'account', 'memberships', 'invoices', 'checkIns', 'tierVerification'));
    }

    public function verifyCustomerTier(Request $request, int $customer): RedirectResponse
    {
        $data = $request->validate([
            'verified' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $this->sql->transaction(function () use ($request, $customer, $data): void {
            $profile = DB::table('KHACHHANG')->where('MAKH', $customer)->lockForUpdate()->first();
            abort_unless($profile, 404);
            $tier = DB::table('LOAIKHACHHANG')->where('MALOAIKH', $profile->MALOAIKH)->first();
            if (! $tier || ! $tier->REQUIRES_VERIFICATION) {
                $this->fail('verified', 'Hạng khách này không yêu cầu xác minh ưu đãi.');
            }

            $active = DB::table('gym_customer_tier_verifications')
                ->where('customer_id', $customer)
                ->where('tier_id', $profile->MALOAIKH)
                ->whereNull('revoked_at')
                ->orderByDesc('id')
                ->first();
            if ($data['verified']) {
                if (! $active) {
                    DB::table('gym_customer_tier_verifications')->insert([
                        'customer_id' => $customer,
                        'tier_id' => $profile->MALOAIKH,
                        'verified_by' => $request->user()->id,
                        'reason' => $data['reason'],
                        'verified_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $this->sql->audit('Xác minh ưu đãi hạng khách', 'KHACHHANG:'.$customer, [
                    'tier_id' => $profile->MALOAIKH,
                    'status' => $active ? 'Đã xác minh trước đó' : 'Đã xác minh',
                    'reason' => $data['reason'],
                ]);

                return;
            }

            if ($active) {
                DB::table('gym_customer_tier_verifications')->where('id', $active->id)->update(['revoked_at' => now(), 'updated_at' => now()]);
            }
            $this->sql->audit('Thu hồi xác minh ưu đãi hạng khách', 'KHACHHANG:'.$customer, [
                'tier_id' => $profile->MALOAIKH,
                'was_verified' => (bool) $active,
                'reason' => $data['reason'],
            ]);
        });

        return back()->with('success', $data['verified'] ? 'Đã xác minh hạng khách.' : 'Đã thu hồi xác minh; ưu đãi không còn áp dụng cho đơn mới.');
    }

    public function edit(Request $request, string $resource, ?int $record = null): View
    {
        [$table, $key, $title] = $this->definition($resource);
        $row = $record ? match ($resource) {
            'products' => GymProduct::query()->find($record),
            'categories' => GymProductCategory::query()->find($record),
            default => DB::table($table)->where($key, $record)->first(),
        } : null;
        abort_if($record && ! $row, 404);
        $fields = $this->fields($resource);
        $options = [];
        foreach (['MALOAISP' => ['LOAISANPHAM', 'MALOAISP', 'TENLOAISP'], 'MACM' => ['CHUYENMON', 'MACM', 'TENCHUYENMON'], 'MALOAIKH' => ['LOAIKHACHHANG', 'MALOAIKH', 'TENLOAI'], 'MACHUCVU' => ['CHUCVU', 'MACHUCVU', 'TENCHUCVU']] as $field => [$lookup, $pk, $label]) {
            if (isset($fields[$field]) || $resource === 'employees') {
                $options[$field] = $lookup === 'LOAISANPHAM'
                    ? GymProductCategory::query()->orderBy($pk)->pluck($label, $pk)
                    : DB::table($lookup)->orderBy($pk)->pluck($label, $pk);
            }
        }
        $account = $record && in_array($resource, ['customers', 'employees'], true) ? User::query()->where($resource === 'customers' ? 'sql_customer_id' : 'sql_employee_id', $record)->first() : null;

        $staffMode = $request->routeIs('staff.products.*');

        return view('gym.admin.edit', ['resource' => $resource, 'title' => $title, 'key' => $key, 'row' => $row, 'fields' => $fields, 'options' => $options, 'account' => $account, 'specialties' => $resource === 'employees' && $record ? DB::table('NHANVIEN_CHUYENMON')->where('MANV', $record)->pluck('MACM')->map(fn ($v) => (int) $v)->all() : [], 'images' => $resource === 'products' && $record ? DB::table('HINHANH')->where('MASP', $record)->orderByDesc('ISMAIN')->orderBy('MAHINH')->get() : [], 'routePrefix' => $staffMode ? 'staff.products' : 'gym.admin', 'staffMode' => $staffMode]);
    }

    public function save(Request $request, string $resource, ?int $record = null): RedirectResponse
    {
        [$table, $key] = $this->definition($resource);
        $rules = [];
        foreach ($this->fields($resource) as $field => $definition) {
            $rules[$field] = $definition[1];
        }
        if (in_array($resource, ['customers', 'employees'], true)) {
            $rules['TENDANGNHAP'][] = Rule::unique($table, 'TENDANGNHAP')->ignore($record, $key);
            $rules['SDT'][] = Rule::unique($table, 'SDT')->ignore($record, $key);
            if ($resource === 'customers') {
                $rules['EMAIL'][] = Rule::unique($table, 'EMAIL')->ignore($record, $key);
            }
            $rules['password'] = [$record ? 'nullable' : 'required', 'string', 'min:8', 'max:72'];
            $rules['is_active'] = ['required', 'boolean'];
        }
        if ($resource === 'employees') {
            $rules['specialties'] = ['nullable', 'array'];
            $rules['specialties.*'] = ['integer', 'distinct', 'exists:CHUYENMON,MACM'];
        }
        $data = $request->validate($rules);
        if (in_array($resource, ['customers', 'employees'], true)) {
            $data += ['SDT' => null, 'EMAIL' => null, 'NGAYSINH' => null, 'GIOITINH' => null];
        }
        try {
            $this->sql->transaction(function () use ($request, $resource, $record, $table, $key, $data): void {
                $row = $record ? match ($resource) {
                    'products' => GymProduct::query()->find($record),
                    'categories' => GymProductCategory::query()->find($record),
                    default => DB::table($table)->where($key, $record)->first(),
                } : null;
                abort_if($record && ! $row, 404);
                if ($resource === 'products') {
                    if (isset($data['GIAKHUYENMAI']) && $data['GIAKHUYENMAI'] > $data['DONGIA']) {
                        $this->fail('GIAKHUYENMAI', 'Giá khuyến mãi không được vượt giá gốc.');
                    }
                    $reserved = DB::table('gym_invoice_lines as l')->join('HOADON as h', 'h.MAHD', '=', 'l.invoice_id')->where('l.kind', 'product')->where('l.item_id', $record)->where('h.TRANGTHAI', 'Chưa thanh toán')->sum('quantity');
                    if ((int) $data['SOLUONGTON'] < (int) $reserved) {
                        $this->fail('SOLUONGTON', 'Tồn kho không được thấp hơn số hàng đang giữ cho đơn chờ.');
                    }
                    if ($row && (int) $row->SOLUONGTON !== (int) $data['SOLUONGTON']) {
                        $request->validate(
                            ['stock_reason' => 'required|string|min:5|max:255'],
                            [
                                'stock_reason.required' => 'Vui lòng nhập lý do thay đổi tồn kho.',
                                'stock_reason.string' => 'Lý do thay đổi tồn kho phải là văn bản.',
                                'stock_reason.min' => 'Lý do thay đổi tồn kho phải có ít nhất 5 ký tự.',
                                'stock_reason.max' => 'Lý do thay đổi tồn kho không được vượt quá 255 ký tự.',
                            ],
                        );
                    }
                }
                if ($resource === 'classes' && $row) {
                    if (DB::table('LICHLOP as s')->where('s.MALOP', $record)->whereNotExists(function ($q) use ($data): void {
                        $q->selectRaw('1')->from('NHANVIEN_CHUYENMON as c')->whereColumn('c.MANV', 's.MANV')->where('c.MACM', $data['MACM']);
                    })->exists()) {
                        $this->fail('MACM', 'Nhân viên phụ trách lịch đã xếp chưa có chuyên môn mới này.');
                    }
                    $used = DB::table('DANGKYLOP')->where('MALOP', $record)->where('TRANGTHAI', 'Còn hiệu lực')->count();
                    $reserved = DB::table('gym_invoice_lines as l')->join('HOADON as h', 'h.MAHD', '=', 'l.invoice_id')->where('l.kind', 'class')->where('l.item_id', $record)->where('h.TRANGTHAI', 'Chưa thanh toán')->count();
                    if (($data['SISOTOIDA'] && $data['SISOTOIDA'] < $used + $reserved) || $data['SOBUOI'] < DB::table('LICHLOP')->where('MALOP', $record)->count()) {
                        $this->fail('SISOTOIDA', 'Sĩ số/số buổi không được thấp hơn đăng ký và lịch đã có.');
                    }
                    if (DB::table('LICHLOP')->where('MALOP', $record)->where(function ($q) use ($data): void {
                        $q->whereDate('NGAYHOC', '<', $data['NGAYBATDAU'])->orWhereDate('NGAYHOC', '>', $data['NGAYKETTHUC']);
                    })->exists()) {
                        $this->fail('NGAYBATDAU', 'Thời gian khóa phải chứa tất cả các buổi đã xếp.');
                    }
                }
                if (in_array($resource, ['customers', 'employees'], true)) {
                    $customer = $resource === 'customers';
                    $link = $customer ? 'sql_customer_id' : 'sql_employee_id';
                    $user = $row ? (User::query()->where($link, $record)->first() ?? $this->identities->importAccount($row, $customer ? 'customer' : 'employee')) : new User;
                    if (! $user) {
                        $this->fail('TENDANGNHAP', 'Tên đăng nhập đã thuộc tài khoản web khác.');
                    }
                    $role = $customer ? 'customer' : (DB::table('CHUCVU')->where('MACHUCVU', $data['MACHUCVU'])->value('TENCHUCVU') === 'Quản lý' ? 'manager' : 'employee');
                    if ($row && $data['TENDANGNHAP'] !== $row->TENDANGNHAP) {
                        $this->fail('TENDANGNHAP', 'Giữ tên đăng nhập để không mất liên kết tài khoản.');
                    }
                    if ($user->exists && $user->role === 'manager' && ($role !== 'manager' || ! $data['is_active']) && (User::query()->where('role', 'manager')->where('is_active', true)->where('id', '!=', $user->id)->count() === 0 || $user->id === $request->user()->id)) {
                        $this->fail('is_active', 'Không tự khóa/hạ quyền mình hoặc quản lý cuối cùng.');
                    }
                    $attrs = ['name' => $data[$customer ? 'TENKH' : 'TENNV'], 'username' => $data['TENDANGNHAP'], 'phone' => $data['SDT'], 'birth_date' => $data['NGAYSINH'], 'gender' => $data['GIOITINH'] === 'Nam' ? 'male' : ($data['GIOITINH'] === 'Nữ' ? 'female' : null), 'role' => $role, 'is_active' => $data['is_active']];
                    if ($customer) {
                        $attrs['email'] = $data['EMAIL'];
                    }
                    if (! empty($data['password'])) {
                        $attrs['password'] = $data['password'];
                    }
                    if (User::query()->where('username', $attrs['username'])->when($user->exists, fn ($q) => $q->where('id', '!=', $user->id))->exists()) {
                        $this->fail('TENDANGNHAP', 'Tên đăng nhập đã có trên web.');
                    }
                    foreach (['phone', 'email'] as $unique) {
                        if (! empty($attrs[$unique]) && User::query()->where($unique, $attrs[$unique])->when($user->exists, fn ($q) => $q->where('id', '!=', $user->id))->exists()) {
                            $this->fail($unique === 'email' ? 'EMAIL' : 'SDT', 'Email hoặc số điện thoại đã được sử dụng.');
                        }
                    }
                    $user->forceFill($attrs)->save();
                    $this->identities->sync($user);
                    $id = $customer ? $this->identities->customer($user) : $this->identities->employee($user);
                    DB::table($table)->where($key, $id)->update([$customer ? 'MALOAIKH' : 'MACHUCVU' => $data[$customer ? 'MALOAIKH' : 'MACHUCVU']]);
                    if ($customer && $row && (string) $row->MALOAIKH !== (string) $data['MALOAIKH']) {
                        DB::table('gym_customer_tier_verifications')->where('customer_id', $id)->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
                    }
                    if (! $customer) {
                        if (DB::table('LICHLOP as s')->join('LOPHOC as l', 'l.MALOP', '=', 's.MALOP')->where('s.MANV', $id)->where('s.GIOKETTHUC', '>', now())->whereNotIn('l.MACM', $data['specialties'] ?? [])->exists()) {
                            $this->fail('specialties', 'Không thể bỏ chuyên môn đang cần cho lịch lớp sắp tới.');
                        }
                        DB::table('NHANVIEN_CHUYENMON')->where('MANV', $id)->delete();
                        foreach ($data['specialties'] ?? [] as $specialty) {
                            DB::table('NHANVIEN_CHUYENMON')->insert(['MANV' => $id, 'MACM' => $specialty]);
                        }
                    }
                } else {
                    $id = $record ?? $this->sql->nextId($table);
                    if ($resource === 'products') {
                        $product = $record ? GymProduct::query()->findOrFail($record) : new GymProduct;
                        $product->fill([$key => $id] + $data)->save();
                    } elseif ($resource === 'categories') {
                        $category = $record ? GymProductCategory::query()->findOrFail($record) : new GymProductCategory;
                        $category->fill([$key => $id] + $data)->save();
                    } elseif ($record) {
                        DB::table($table)->where($key, $record)->update($data);
                    } else {
                        DB::table($table)->insert([$key => $id] + $data);
                    }
                }
                $auditData = [];
                if ($resource === 'products') {
                    $auditData = ['old_stock' => $row?->SOLUONGTON, 'new_stock' => $data['SOLUONGTON'], 'reason' => $request->input('stock_reason')];
                    foreach (['DONGIA', 'GIAKHUYENMAI'] as $field) {
                        if ((string) ($row?->$field ?? '') !== (string) ($data[$field] ?? '')) {
                            $auditData['old_values'][$field] = $row?->$field;
                            $auditData['new_values'][$field] = $data[$field] ?? null;
                        }
                    }
                } elseif ($resource === 'tiers') {
                    foreach (['MUCGIAM', 'REQUIRES_VERIFICATION'] as $field) {
                        if ((string) ($row?->$field ?? '') !== (string) ($data[$field] ?? '')) {
                            $auditData['old_values'][$field] = $row?->$field;
                            $auditData['new_values'][$field] = $data[$field] ?? null;
                        }
                    }
                }
                $this->sql->audit($record ? 'Cập nhật' : 'Tạo', $table.':'.$id, $auditData);
            });
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '45000'], true)) {
                $this->fail('record', 'Dữ liệu bị trùng hoặc vi phạm ràng buộc SQL. Hãy kiểm tra lại các trường.');
            }
            throw $exception;
        }

        $catalogSyncQueued = ! in_array($resource, ['products', 'categories'], true) || $this->queueProductCatalogSync();

        $message = $request->routeIs('staff.products.*')
            ? 'Đã lưu sản phẩm và cập nhật tồn kho.'
            : 'Đã lưu dữ liệu.';
        if (in_array($resource, ['products', 'categories'], true)) {
            $message = $catalogSyncQueued
                ? 'Đã lưu dữ liệu. Chatbot sẽ cập nhật danh mục trong nền.'
                : 'Đã lưu dữ liệu trong MySQL. Hãy mở Tài liệu chatbot để thử đồng bộ lại danh mục.';
        }

        return ($request->routeIs('staff.products.*')
            ? redirect()->route('staff.products.index')
            : redirect()->route('gym.admin.index', $resource))->with('success', $message);
    }

    public function destroy(string $resource, int $record): RedirectResponse
    {
        abort_unless(auth()->user()?->role === 'manager', 403);
        [$table, $key] = $this->definition($resource);
        abort_if(in_array($resource, ['customers', 'employees'], true), 403);
        try {
            $this->sql->transaction(function () use ($table, $key, $record, $resource): void {
                if ($resource === 'products' && DB::table('gym_invoice_lines as line')
                    ->join('HOADON as invoice', 'invoice.MAHD', '=', 'line.invoice_id')
                    ->where('line.kind', 'product')
                    ->where('line.item_id', $record)
                    ->where('invoice.TRANGTHAI', 'Chưa thanh toán')
                    ->exists()) {
                    $this->fail('record', 'Sản phẩm đang được giữ trong đơn chưa thanh toán nên chưa thể xóa. Hãy xử lý đơn trước.');
                }

                if ($resource === 'classes') {
                    $hasPendingInvoice = DB::table('gym_invoice_lines as line')
                        ->join('HOADON as invoice', 'invoice.MAHD', '=', 'line.invoice_id')
                        ->where('line.kind', 'class')
                        ->where('line.item_id', $record)
                        ->where('invoice.TRANGTHAI', 'Chưa thanh toán')
                        ->exists();
                    $hasRegistrations = DB::table('DANGKYLOP')->where('MALOP', $record)->exists();
                    $hasSchedules = DB::table('LICHLOP')->where('MALOP', $record)->exists();

                    if ($hasPendingInvoice || $hasRegistrations || $hasSchedules) {
                        $this->fail('record', 'Lớp đã có đơn, đăng ký hoặc lịch học liên quan nên chưa thể xóa. Hãy lưu lại để bảo toàn lịch sử.');
                    }
                }

                if ($resource === 'products') {
                    DB::table('HINHANH')->where('MASP', $record)->delete();
                }
                $deleted = match ($resource) {
                    'products' => GymProduct::query()->whereKey($record)->delete(),
                    'categories' => GymProductCategory::query()->whereKey($record)->delete(),
                    default => DB::table($table)->where($key, $record)->delete(),
                };
                abort_unless($deleted, 404);
                $this->sql->audit('Xóa danh mục chưa sử dụng', $table.':'.$record);
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23000') {
                $this->fail('record', 'Dữ liệu đã được sử dụng, không thể xóa.');
            }
            throw $exception;
        }

        $catalogSyncQueued = ! in_array($resource, ['products', 'categories'], true) || $this->queueProductCatalogSync();
        $message = in_array($resource, ['products', 'categories'], true)
            ? ($catalogSyncQueued ? 'Đã xóa. Chatbot sẽ cập nhật danh mục trong nền.' : 'Đã xóa trong MySQL. Hãy mở Tài liệu chatbot để thử đồng bộ lại danh mục.')
            : 'Đã xóa.';

        return back()->with('success', $message);
    }

    public function image(Request $request, int $product, ?int $image = null): RedirectResponse
    {
        abort_unless(GymProduct::query()->whereKey($product)->exists(), 404);
        $request->validate(['image' => [$image ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'], 'main' => 'nullable|boolean', 'remove' => 'nullable|boolean']);
        $path = $request->file('image')?->store('products', 'public');
        $this->sql->transaction(function () use ($request, $product, $image, $path): void {
            if ($image) {
                abort_unless(DB::table('HINHANH')->where('MASP', $product)->where('MAHINH', $image)->exists(), 404);
            }
            if ($request->boolean('remove')) {
                DB::table('HINHANH')->where('MASP', $product)->where('MAHINH', $image)->delete();
            } else {
                if ($image || $request->boolean('main')) {
                    DB::table('HINHANH')->where('MASP', $product)->update(['ISMAIN' => 0]);
                }
                if ($image) {
                    DB::table('HINHANH')->where('MAHINH', $image)->update(['ISMAIN' => 1]);
                } else {
                    DB::table('HINHANH')->insert(['MAHINH' => $this->sql->nextId('HINHANH'), 'MASP' => $product, 'URL' => $path, 'ISMAIN' => $request->boolean('main') ? 1 : 0]);
                }
            }
            if (! DB::table('HINHANH')->where('MASP', $product)->where('ISMAIN', 1)->exists()) {
                $first = DB::table('HINHANH')->where('MASP', $product)->orderBy('MAHINH')->value('MAHINH');
                DB::table('HINHANH')->where('MAHINH', $first)->update(['ISMAIN' => 1]);
            }
            $this->sql->audit('Cập nhật album', 'SANPHAM:'.$product);
        });

        return back()->with('success', 'Đã cập nhật ảnh sản phẩm.');
    }

    public function fields(string $resource): array
    {
        return match ($resource) {
            'positions' => ['TENCHUCVU' => ['Tên chức vụ', ['required', 'string', 'max:100']]],
            'tiers' => ['TENLOAI' => ['Tên hạng', ['required', 'string', 'max:100']], 'MUCGIAM' => ['Giảm giá (%)', ['nullable', 'integer', 'between:0,99']], 'REQUIRES_VERIFICATION' => ['Cần xác minh trước khi áp ưu đãi', ['required', 'boolean']]],
            'specialties' => ['TENCHUYENMON' => ['Tên chuyên môn', ['required', 'string', 'max:100']], 'MOTA' => ['Mô tả', ['nullable', 'string', 'max:10000']]],
            'categories' => ['TENLOAISP' => ['Tên nhóm sản phẩm', ['required', 'string', 'max:100']]],
            'products' => ['TENSP' => ['Tên sản phẩm', ['required', 'string', 'max:255']], 'MALOAISP' => ['Nhóm', ['required', 'integer', 'exists:LOAISANPHAM,MALOAISP']], 'DONGIA' => ['Giá gốc (VNĐ)', ['required', 'integer', 'min:1', 'max:1000000000000']], 'GIAKHUYENMAI' => ['Giá khuyến mãi (VNĐ)', ['nullable', 'integer', 'min:1', 'max:1000000000000']], 'SOLUONGTON' => ['Tồn kho', ['required', 'integer', 'min:0', 'max:100000000']], 'HANG' => ['Hãng', ['nullable', 'string', 'max:100']], 'XUATXU' => ['Xuất xứ', ['nullable', 'string', 'max:100']], 'BAOHANH' => ['Bảo hành', ['nullable', 'string', 'max:100']], 'MOTACHUNG' => ['Mô tả chung', ['nullable', 'string', 'max:10000']], 'MOTACHITIET' => ['Mô tả chi tiết', ['nullable', 'string', 'max:50000']]],
            'classes' => ['TENLOP' => ['Tên lớp', ['required', 'string', 'max:150']], 'MACM' => ['Chuyên môn', ['required', 'integer', 'exists:CHUYENMON,MACM']], 'HOCPHI' => ['Học phí (VNĐ)', ['required', 'integer', 'min:1', 'max:1000000000000']], 'NGAYBATDAU' => ['Ngày bắt đầu', ['required', 'date']], 'NGAYKETTHUC' => ['Ngày kết thúc', ['required', 'date', 'after_or_equal:NGAYBATDAU']], 'SOBUOI' => ['Số buổi', ['required', 'integer', 'min:1', 'max:1000']], 'SISOTOIDA' => ['Sĩ số tối đa (trống: không giới hạn)', ['nullable', 'integer', 'min:1', 'max:10000']]],
            'customers', 'employees' => ($resource === 'customers' ? ['TENKH' => ['Họ tên', ['required', 'string', 'max:150']], 'MALOAIKH' => ['Hạng khách', ['required', 'integer', 'exists:LOAIKHACHHANG,MALOAIKH']], 'EMAIL' => ['Email', ['nullable', 'email', 'max:100']]] : ['TENNV' => ['Họ tên', ['required', 'string', 'max:150']], 'MACHUCVU' => ['Chức vụ', ['required', 'integer', 'exists:CHUCVU,MACHUCVU']]]) + ['TENDANGNHAP' => ['Tên đăng nhập', ['required', 'alpha_dash', 'max:50']], 'SDT' => ['Điện thoại', ['nullable', 'string', 'max:20']], 'NGAYSINH' => ['Ngày sinh', ['nullable', 'date', 'before:today']], 'GIOITINH' => ['Giới tính', ['nullable', 'in:Nam,Nữ']]],
            default => abort(404),
        };
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function queueProductCatalogSync(): bool
    {
        try {
            $this->productCatalog->stage();
            SyncRagProductCatalog::dispatch();

            return true;
        } catch (Throwable $exception) {
            Log::warning('The Gym product catalog could not be queued for RAG synchronization.', [
                'exception_class' => $exception::class,
            ]);

            return false;
        }
    }
}
