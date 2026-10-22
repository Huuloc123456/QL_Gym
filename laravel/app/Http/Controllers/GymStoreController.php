<?php

namespace App\Http\Controllers;

use App\Models\GymPackage;
use App\Services\GymCommerceService;
use App\Services\GymIdentityService;
use App\Services\GymPortalService;
use App\Services\OriginalGymSql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GymStoreController extends Controller
{
    public function __construct(private GymCommerceService $shop, private GymIdentityService $identities, private OriginalGymSql $sql, private GymPortalService $portal) {}

    public function index(Request $request): View
    {
        $data = $request->validate(['q' => 'nullable|string|max:150', 'category' => 'nullable|integer|exists:LOAISANPHAM,MALOAISP', 'brand' => 'nullable|string|max:100', 'origin' => 'nullable|string|max:100', 'min' => 'nullable|integer|min:0|max:1000000000000', 'max' => 'nullable|integer|min:0|max:1000000000000', 'sort' => 'nullable|in:name,price_up,price_down,newest', 'stock' => 'nullable|in:1', 'sale' => 'nullable|in:1']);
        $query = DB::table('SANPHAM as p')->leftJoin('LOAISANPHAM as c', 'c.MALOAISP', '=', 'p.MALOAISP')->select('p.*', 'c.TENLOAISP')
            ->selectSub(DB::table('HINHANH')->select('URL')->whereColumn('MASP', 'p.MASP')->orderByDesc('ISMAIN')->orderBy('MAHINH')->limit(1), 'image');
        $query->when($data['q'] ?? null, fn ($q, $value) => $q->where('p.TENSP', 'like', '%'.$value.'%'))
            ->when($data['category'] ?? null, fn ($q, $value) => $q->where('p.MALOAISP', $value))
            ->when($data['brand'] ?? null, fn ($q, $value) => $q->where('p.HANG', $value))
            ->when($data['origin'] ?? null, fn ($q, $value) => $q->where('p.XUATXU', $value));
        if (isset($data['min'])) {
            $query->whereRaw('LEAST(DONGIA, COALESCE(GIAKHUYENMAI, DONGIA)) >= ?', [$data['min']]);
        }
        if (isset($data['max'])) {
            $query->whereRaw('LEAST(DONGIA, COALESCE(GIAKHUYENMAI, DONGIA)) <= ?', [$data['max']]);
        }
        if (isset($data['stock'])) {
            $query->where('SOLUONGTON', '>', 0);
        }
        if (isset($data['sale'])) {
            $query->whereColumn('GIAKHUYENMAI', '<', 'DONGIA');
        }
        match ($data['sort'] ?? 'name') {
            'name' => $query->orderBy('TENSP'),
            'price_up' => $query->orderByRaw('LEAST(DONGIA, COALESCE(GIAKHUYENMAI, DONGIA)) ASC'),
            'price_down' => $query->orderByRaw('LEAST(DONGIA, COALESCE(GIAKHUYENMAI, DONGIA)) DESC'),
            default => $query->orderByDesc('p.MASP'),
        };

        return view('gym.products', ['products' => $query->orderBy('p.MASP')->paginate(15)->withQueryString(), 'categories' => DB::table('LOAISANPHAM')->orderBy('MALOAISP')->get(), 'brands' => DB::table('SANPHAM')->whereNotNull('HANG')->distinct()->orderBy('HANG')->pluck('HANG'), 'origins' => DB::table('SANPHAM')->whereNotNull('XUATXU')->distinct()->orderBy('XUATXU')->pluck('XUATXU')]);
    }

    public function show(int $product): View
    {
        return view('gym.product', ['product' => $this->shop->item('product', $product), 'images' => DB::table('HINHANH')->where('MASP', $product)->orderByDesc('ISMAIN')->orderBy('MAHINH')->get()->filter(fn ($image) => OriginalGymSql::image($image->URL, $product) !== asset('images/product-placeholder.svg')), 'available' => $this->shop->available($product)]);
    }

    public function cart(Request $request): View
    {
        $customer = $this->identities->customer($request->user());
        $lines = $this->shop->cart($request->user());
        $total = array_sum(array_column($lines, 'total'));
        $tier = DB::table('KHACHHANG as k')->leftJoin('LOAIKHACHHANG as t', 't.MALOAIKH', '=', 'k.MALOAIKH')->where('k.MAKH', $customer)->select('t.TENLOAI', 't.MUCGIAM')->first();

        return view('gym.cart', ['lines' => $lines, 'total' => $total, 'tier' => $tier, 'settings' => $this->portal->settings(), 'hasProducts' => collect($lines)->contains(fn ($line) => $line->item->kind === 'product'), 'discount' => intdiv($total * (int) $tier->MUCGIAM, 100), 'addresses' => DB::table('DIACHI')->where('MAKH', $customer)->orderByDesc('LADIACHIMACDINH')->orderBy('MADC')->get()]);
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate(['kind' => 'required|in:product,package,class', 'item' => 'required_without:gym_package_id|integer|min:1', 'gym_package_id' => 'nullable|integer|exists:gym_packages,id', 'quantity' => 'required|integer|min:1|max:99']);
        $item = $data['item'] ?? null;
        if (isset($data['gym_package_id'])) {
            abort_unless($data['kind'] === 'package', 422);
            $package = GymPackage::query()->where('is_active', true)->findOrFail($data['gym_package_id']);
            $item = $this->identities->syncPackage($package);
        }
        $this->shop->add($request->user(), $data['kind'], (int) $item, $data['quantity']);

        return redirect()->route('gym.cart')->with('success', 'Đã thêm vào giỏ hàng.');
    }

    public function change(Request $request, int $line): RedirectResponse
    {
        $data = $request->validate(['quantity' => 'required|integer|min:0|max:99']);
        $this->shop->changeCart($request->user(), $line, $data['quantity']);

        return back()->with('success', 'Đã cập nhật giỏ hàng.');
    }

    public function checkout(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => 'required|uuid', 'mode' => 'required|in:pickup,delivery', 'address_id' => 'nullable|integer', 'recipient' => 'required_if:mode,delivery|nullable|string|max:150', 'phone' => 'required_if:mode,delivery|nullable|string|max:20', 'payment_method' => 'required|in:cash,bank_transfer,vnpay', 'start_date' => 'nullable|date|after_or_equal:today']);
        $invoice = $this->shop->checkout($request->user(), $data);

        return redirect()->route('gym.invoices.show', $invoice)->with('success', 'Đã đặt đơn. Nhân viên sẽ xác nhận sau khi nhận đủ tiền.');
    }

    public function addresses(Request $request): View
    {
        return view('gym.addresses', ['addresses' => DB::table('DIACHI')->where('MAKH', $this->identities->customer($request->user()))->orderByDesc('LADIACHIMACDINH')->orderBy('MADC')->get()]);
    }

    public function addressSave(Request $request, ?int $address = null): RedirectResponse
    {
        $data = $request->validate(['TINHTHANHPHO' => 'required|string|max:100', 'QUANHUYEN' => 'required|string|max:100', 'PHUONGXA' => 'required|string|max:100', 'DIACHICUTHE' => 'required|string|max:255', 'KINHDO' => 'nullable|numeric|between:-180,180', 'VIDO' => 'nullable|numeric|between:-90,90', 'LADIACHIMACDINH' => 'nullable|boolean']);
        $this->sql->transaction(function () use ($request, $data, $address): void {
            $customer = $this->identities->customer($request->user());
            $query = DB::table('DIACHI')->where('MAKH', $customer);
            if ($address) {
                abort_unless((clone $query)->where('MADC', $address)->exists(), 404);
            }
            $default = $request->boolean('LADIACHIMACDINH') || ! (clone $query)->exists();
            if ($default) {
                (clone $query)->update(['LADIACHIMACDINH' => 0]);
            }
            $data['LADIACHIMACDINH'] = $default ? 1 : 0;
            if ($address) {
                (clone $query)->where('MADC', $address)->update($data);
            } else {
                DB::table('DIACHI')->insert($data + ['MAKH' => $customer, 'NGAYTHEM' => now()]);
            }
            if (! (clone $query)->where('LADIACHIMACDINH', 1)->exists()) {
                $first = (clone $query)->orderBy('MADC')->value('MADC');
                (clone $query)->where('MADC', $first)->update(['LADIACHIMACDINH' => 1]);
            }
        });

        return redirect()->route('gym.addresses')->with('success', 'Đã lưu địa chỉ.');
    }

    public function addressDelete(Request $request, int $address): RedirectResponse
    {
        $this->sql->transaction(function () use ($request, $address): void {
            $customer = $this->identities->customer($request->user());
            $query = DB::table('DIACHI')->where('MAKH', $customer);
            abort_unless((clone $query)->where('MADC', $address)->delete(), 404);
            if (! (clone $query)->where('LADIACHIMACDINH', 1)->exists()) {
                $first = (clone $query)->orderBy('MADC')->value('MADC');
                (clone $query)->where('MADC', $first)->update(['LADIACHIMACDINH' => 1]);
            }
        });

        return back()->with('success', 'Đã xóa địa chỉ; thông tin trên đơn cũ vẫn được giữ.');
    }

    public function loginHistory(Request $request): View
    {
        return view('gym.login-history', ['history' => DB::table('LOGINHISTORY')->where('USERID', $this->identities->customer($request->user()))->select('ID', 'IPADDRESS', 'LOGINTIME', 'STATUS')->orderByDesc('LOGINTIME')->orderByDesc('ID')->paginate(30)]);
    }
}
