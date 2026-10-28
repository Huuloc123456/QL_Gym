<?php

namespace App\Services;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GymCommerceService
{
    public function __construct(private OriginalGymSql $sql, private GymIdentityService $identities, private MembershipPeriod $period, private GymTrainingService $training, private GymPortalService $portal) {}

    public function item(string $kind, int $id): object
    {
        [$table, $key, $name, $price] = match ($kind) {
            'product' => ['SANPHAM', 'MASP', 'TENSP', 'DONGIA'],
            'package' => ['GOITAP', 'MAGOITAP', 'TENGOI', 'GIA'],
            'class' => ['LOPHOC', 'MALOP', 'TENLOP', 'HOCPHI'],
            'pt' => ['NHANVIEN', 'MANV', 'TENNV', null],
            default => abort(404),
        };
        $row = DB::table($table)->where($key, $id)->first();
        abort_unless($row, 404);
        if ($kind === 'package' && GymPackage::query()->where('sql_package_id', $id)->where('is_active', false)->exists()) {
            abort(404);
        }
        $row->item_id = $id;
        $row->kind = $kind;
        $row->item_name = $row->$name;
        $row->unit_price = $price ? (int) $row->$price : (int) DB::table('gym_pt_rates')->where('employee_id', $id)->value('price');
        if ($kind === 'product' && $row->GIAKHUYENMAI !== null && $row->GIAKHUYENMAI < $row->DONGIA) {
            $row->unit_price = (int) $row->GIAKHUYENMAI;
        }

        return $row;
    }

    public function available(int $product): int
    {
        $reserved = DB::table('gym_invoice_lines as l')->join('HOADON as h', 'h.MAHD', '=', 'l.invoice_id')
            ->where('l.kind', 'product')->where('l.item_id', $product)->where('h.TRANGTHAI', 'Chưa thanh toán')->sum('l.quantity');

        return max(0, (int) DB::table('SANPHAM')->where('MASP', $product)->value('SOLUONGTON') - (int) $reserved);
    }

    public function availableProductsQuery(): Builder
    {
        $products = DB::table('SANPHAM')->select('SANPHAM.*')
            ->selectSub(DB::table('gym_invoice_lines as lines')->join('HOADON as invoices', 'invoices.MAHD', '=', 'lines.invoice_id')
                ->selectRaw('COALESCE(SUM(lines.quantity), 0)')->whereColumn('lines.item_id', 'SANPHAM.MASP')
                ->where('lines.kind', 'product')->where('invoices.TRANGTHAI', 'Chưa thanh toán'), 'reserved_quantity');

        return DB::query()->fromSub($products, 'p')->select('p.*')
            ->selectRaw('GREATEST(0, p.SOLUONGTON - p.reserved_quantity) AS available_quantity');
    }

    public function cart(User $user): array
    {
        $customer = $this->identities->customer($user);

        return DB::table('CHITIETGIOHANG')->where('MAKH', $customer)->orderBy('MACHITIETGH')->get()->map(function ($line): object {
            $kind = $line->MASP ? 'product' : ($line->MAGOITAP ? 'package' : 'class');
            try {
                $item = $this->item($kind, (int) ($line->MASP ?: $line->MAGOITAP ?: $line->MALOP));
                $line->unavailable = false;
            } catch (NotFoundHttpException) {
                $item = (object) ['kind' => $kind, 'item_name' => 'Mặt hàng không còn mở bán', 'unit_price' => 0];
                $line->unavailable = true;
            }
            $line->item = $item;
            $line->total = $item->unit_price * (int) $line->SOLUONG;

            return $line;
        })->all();
    }

    public function add(User $user, string $kind, int $id, int $quantity): void
    {
        if ($quantity < 1 || $quantity > 99 || ($kind !== 'product' && $quantity !== 1)) {
            $this->fail('quantity', 'Số lượng không hợp lệ. Gói và lớp chỉ đăng ký một suất.');
        }
        $this->sql->transaction(function () use ($user, $kind, $id, $quantity): void {
            $customer = $this->identities->customer($user);
            $column = match ($kind) {
                'product' => 'MASP', 'package' => 'MAGOITAP', 'class' => 'MALOP', default => abort(404)
            };
            $item = $this->item($kind, $id);
            $line = DB::table('CHITIETGIOHANG')->where('MAKH', $customer)->where($column, $id)->first();
            if (! $line && DB::table('CHITIETGIOHANG')->where('MAKH', $customer)->count() >= 100) {
                $this->fail('cart', 'Mỗi đơn tối đa 100 mặt hàng khác nhau.');
            }
            $count = $kind === 'product' ? (int) ($line->SOLUONG ?? 0) + $quantity : 1;
            if ($count > 99 || ($kind === 'product' && $count > $this->available($id))) {
                $this->fail('quantity', 'Số lượng vượt quá số hàng có thể đặt.');
            }
            if ($kind === 'package' && DB::table('CHITIETGIOHANG')->where('MAKH', $customer)->whereNotNull('MAGOITAP')->where('MAGOITAP', '!=', $id)->exists()) {
                $this->fail('cart', 'Mỗi lần thanh toán chỉ chọn một gói tập để chốt đúng thời hạn.');
            }
            $data = ['MAKH' => $customer, $column => $id, 'SOLUONG' => $count, 'DONGIA' => $item->unit_price, 'NGAYTHEM' => now()];
            if ($line) {
                DB::table('CHITIETGIOHANG')->where('MACHITIETGH', $line->MACHITIETGH)->update($data);
            } else {
                DB::table('CHITIETGIOHANG')->insert(['MACHITIETGH' => $this->sql->nextId('CHITIETGIOHANG')] + $data);
            }
        });
    }

    public function changeCart(User $user, int $id, int $quantity): void
    {
        $this->sql->transaction(function () use ($user, $id, $quantity): void {
            $customer = $this->identities->customer($user);
            $line = DB::table('CHITIETGIOHANG')->where('MACHITIETGH', $id)->where('MAKH', $customer)->first();
            abort_unless($line, 404);
            if ($quantity === 0) {
                DB::table('CHITIETGIOHANG')->where('MACHITIETGH', $id)->delete();

                return;
            }
            if ((! $line->MASP && $quantity !== 1) || ($line->MASP && $quantity > $this->available((int) $line->MASP))) {
                $this->fail('quantity', 'Số lượng không hợp lệ hoặc không đủ hàng.');
            }
            DB::table('CHITIETGIOHANG')->where('MACHITIETGH', $id)->update(['SOLUONG' => $quantity]);
        });
    }

    public function checkout(User $user, array $data, ?array $pt = null): int
    {
        return $this->sql->transaction(function () use ($user, $data, $pt): int {
            $this->portal->expireOrders();
            $customer = $this->identities->customer($user);
            $repeat = DB::table('gym_fulfillments')->where('checkout_token', $data['token'])->first();
            if ($repeat) {
                abort_unless(DB::table('HOADON')->where('MAHD', $repeat->invoice_id)->where('MAKH', $customer)->exists(), 404);

                return (int) $repeat->invoice_id;
            }
            $cart = $pt ? [(object) ['item' => $this->item('pt', $pt['employee']), 'SOLUONG' => $pt['sessions']]] : $this->cart($user);
            if (count($cart) === 0) {
                $this->fail('cart', 'Giỏ hàng đang trống.');
            }
            if (collect($cart)->contains(fn ($line) => $line->unavailable ?? false)) {
                $this->fail('cart', 'Có mặt hàng không còn mở bán. Hãy bỏ khỏi giỏ trước khi đặt hàng.');
            }
            $total = 0;
            $lines = [];
            $hasProducts = false;
            foreach ($cart as $entry) {
                $item = $entry->item;
                $quantity = (int) $entry->SOLUONG;
                $details = [];
                if ($item->kind === 'product') {
                    $hasProducts = true;
                    if ($quantity > $this->available($item->item_id)) {
                        $this->fail('cart', $item->item_name.' không đủ hàng.');
                    }
                } elseif ($item->kind === 'package') {
                    $start = $data['start_date'] ?? now()->toDateString();
                    $this->assertPackagePeriod($customer, $start, (int) $item->THOIHAN);
                    $details = ['start_date' => $start, 'months' => (int) $item->THOIHAN];
                } elseif ($item->kind === 'class') {
                    $this->assertClassAvailable($customer, $item);
                } elseif ($item->kind === 'pt') {
                    if ($item->unit_price <= 0 || ! DB::table('NHANVIEN_CHUYENMON')->where('MANV', $item->item_id)->exists() || DB::table('CHUCVU')->where('MACHUCVU', $item->MACHUCVU)->value('TENCHUCVU') !== 'PT' || User::query()->where('sql_employee_id', $item->item_id)->where('is_active', false)->exists()) {
                        $this->fail('employee', 'PT chưa sẵn sàng nhận đăng ký hoặc chưa được thiết lập giá/chuyên môn.');
                    }
                    $details = ['sessions' => $quantity];
                }
                $total += $item->unit_price * $quantity;
                $lines[] = ['kind' => $item->kind, 'item_id' => $item->item_id, 'name' => $item->item_name, 'quantity' => $quantity, 'unit_price' => $item->unit_price, 'details' => json_encode($details)];
            }
            $address = null;
            $mode = $hasProducts ? $data['mode'] : 'pickup';
            if ($mode === 'delivery') {
                $address = DB::table('DIACHI')->where('MADC', $data['address_id'] ?? 0)->where('MAKH', $customer)->first();
                if (! $address) {
                    $this->fail('address_id', 'Chọn địa chỉ giao hàng của bạn.');
                }
            }
            $percent = (int) DB::table('KHACHHANG as k')->leftJoin('LOAIKHACHHANG as t', 't.MALOAIKH', '=', 'k.MALOAIKH')->where('k.MAKH', $customer)->value('t.MUCGIAM');
            $discount = intdiv($total * $percent, 100);
            $settings = $this->portal->settings();
            $shipping = $mode === 'delivery' ? (int) $settings->shipping_fee : 0;
            $deadline = now()->addHours((int) $settings->payment_hours);
            $invoice = $this->sql->nextId('HOADON');
            DB::table('HOADON')->insert(['MAHD' => $invoice, 'MAKH' => $customer, 'NGAYLAP' => now(), 'TONGTIEN' => $total, 'GIAMGIA' => $discount, 'THANHTIEN' => $total - $discount + $shipping, 'TRANGTHAI' => 'Chưa thanh toán']);
            foreach ($lines as $line) {
                DB::table('gym_invoice_lines')->insert(['invoice_id' => $invoice] + $line);
            }
            DB::table('gym_fulfillments')->insert(['invoice_id' => $invoice, 'mode' => $mode, 'payment_method' => $data['payment_method'], 'recipient' => $data['recipient'] ?? $user->name, 'phone' => $data['phone'] ?? $user->phone, 'address' => $address ? json_encode($address, JSON_UNESCAPED_UNICODE) : null, 'checkout_token' => $data['token'], 'shipping_fee' => $shipping, 'expires_at' => $deadline, 'created_at' => now(), 'updated_at' => now()]);
            $this->portal->invoiceMessage($invoice, 'created', 'Đã tiếp nhận đơn hàng', 'Đơn #'.$invoice.' cần thanh toán '.number_format($total - $discount + $shipping, 0, ',', '.').'đ trước '.$deadline->format('H:i d/m/Y').'.');
            if (! $pt) {
                DB::table('CHITIETGIOHANG')->where('MAKH', $customer)->delete();
            }
            $this->sql->audit('Đặt hàng', 'HOADON:'.$invoice);

            return $invoice;
        });
    }

    public function receive(int $invoice, User $staff, array $data): void
    {
        abort_unless($staff->canManageOrders(), 403);
        $this->sql->transaction(function () use ($invoice, $staff, $data): void {
            $bill = $this->invoice($invoice);
            $repeat = DB::table('gym_receipts')->where('token', $data['token'])->first();
            if ($repeat) {
                abort_unless((int) $repeat->invoice_id === $invoice && (int) $repeat->amount === (int) $data['amount'], 422);

                return;
            }
            $this->assertInvoiceHasDetails($invoice);
            $this->assertNoPaymentReview($invoice);
            $this->assertBeforeDeadline($invoice);
            if ($bill->TRANGTHAI !== 'Chưa thanh toán' || (int) $data['amount'] > (int) $bill->THANHTIEN - $this->received($invoice)) {
                $this->fail('amount', 'Khoản thu vượt số còn phải thu hoặc hóa đơn đã đóng.');
            }
            DB::table('gym_receipts')->insert(['invoice_id' => $invoice, 'user_id' => $staff->id, 'amount' => $data['amount'], 'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'token' => $data['token'], 'created_at' => now()]);
            $this->portal->invoiceMessage($invoice, 'receipt:'.$data['token'], 'Đã ghi nhận thanh toán', 'Đơn #'.$invoice.' đã nhận thêm '.number_format($data['amount'], 0, ',', '.').'đ. Nhân viên sẽ chốt hóa đơn khi thu đủ.');
            $this->sql->audit('Thu tiền', 'HOADON:'.$invoice, ['amount' => $data['amount']]);
        });
    }

    public function confirm(int $invoice, User $staff, ?string $startDate = null): void
    {
        abort_unless($staff->canManageOrders(), 403);
        $this->confirmInvoice($invoice, $staff, $startDate);
    }

    public function confirmOnline(int $invoice): void
    {
        $this->confirmInvoice($invoice, null, null);
    }

    private function confirmInvoice(int $invoice, ?User $staff, ?string $startDate): void
    {
        $this->sql->transaction(function () use ($invoice, $staff, $startDate): void {
            $bill = $this->invoice($invoice);
            if ($bill->TRANGTHAI === 'Đã thanh toán') {
                return;
            }
            $this->assertInvoiceHasDetails($invoice);
            $this->assertNoPaymentReview($invoice);
            $this->assertBeforeDeadline($invoice);
            if ($bill->TRANGTHAI !== 'Chưa thanh toán' || $this->received($invoice) < (int) $bill->THANHTIEN) {
                $this->fail('payment', 'Hóa đơn phải đang chờ và đã thu đủ tiền.');
            }
            $lines = DB::table('gym_invoice_lines')->where('invoice_id', $invoice)->orderBy('id')->get();
            foreach ($lines as $line) {
                $details = json_decode($line->details, true);
                $record = ['MACTHD' => $this->sql->nextId('CHITIETHOADON'), 'MAHD' => $invoice, 'SOLUONG' => $line->quantity, 'DONGIA' => $line->unit_price];
                if ($line->kind === 'product') {
                    $stock = (int) DB::table('SANPHAM')->where('MASP', $line->item_id)->value('SOLUONGTON');
                    if ($stock < $line->quantity) {
                        $this->fail('stock', 'Tồn kho không đủ, cần kiểm tra trước khi chốt.');
                    }
                    $record['MASP'] = $line->item_id;
                } elseif ($line->kind === 'package') {
                    $start = $startDate ?? $details['start_date'];
                    $expiry = $this->assertPackagePeriod((int) $bill->MAKH, $start, $details['months']);
                    $registration = $this->sql->nextId('DANGKYGOITAP');
                    DB::table('DANGKYGOITAP')->insert(['MADKGT' => $registration, 'MAKH' => $bill->MAKH, 'MAGOITAP' => $line->item_id, 'NGAYDANGKY' => now(), 'NGAYBATDAU' => $start, 'NGAYKETTHUC' => $expiry->subDay()->endOfDay(), 'TRANGTHAI' => 'Còn hiệu lực']);
                    $record['MADKGT'] = $registration;
                    $details['start_date'] = $start;
                    DB::table('gym_invoice_lines')->where('id', $line->id)->update(['details' => json_encode($details)]);
                    $this->cacheMembership($bill, $line, $registration, $start, $expiry, $staff);
                } elseif ($line->kind === 'class') {
                    $class = $this->item('class', (int) $line->item_id);
                    $this->assertClassAvailable((int) $bill->MAKH, $class, $invoice);
                    $registration = $this->sql->nextId('DANGKYLOP');
                    DB::table('DANGKYLOP')->insert(['MADKLOP' => $registration, 'MAKH' => $bill->MAKH, 'MALOP' => $line->item_id, 'NGAYDANGKY' => now(), 'TRANGTHAI' => 'Còn hiệu lực']);
                    $record['MADKLOP'] = $registration;
                } else {
                    $registration = $this->sql->nextId('DANGKYPT');
                    DB::table('DANGKYPT')->insert(['MADKPT' => $registration, 'MAKH' => $bill->MAKH, 'MANV' => $line->item_id, 'SOBUOI' => $line->quantity, 'GIAMOIBUOI' => $line->unit_price, 'NGAYDANGKY' => now(), 'TRANGTHAI' => 'Còn hiệu lực']);
                    $record['MADKPT'] = $registration;
                    $record['SOLUONG'] = $line->quantity;
                }
                // The original trigger alone deducts product stock here.
                DB::table('CHITIETHOADON')->insert($record);
            }
            DB::table('HOADON')->where('MAHD', $invoice)->update(['TRANGTHAI' => 'Đã thanh toán']);
            DB::table('gym_fulfillments')->where('invoice_id', $invoice)->update(['paid_at' => now(), 'updated_at' => now()]);
            $this->portal->invoiceMessage($invoice, 'paid', 'Đã xác nhận hóa đơn', 'Hóa đơn #'.$invoice.' đã được xác nhận thanh toán. Xem đơn để theo dõi dịch vụ hoặc giao nhận.');
            $this->sql->audit('Xác nhận thanh toán', 'HOADON:'.$invoice);
        });
    }

    public function cancel(int $invoice, User $user): void
    {
        $this->sql->transaction(function () use ($invoice, $user): void {
            $bill = $this->invoice($invoice, $user);
            if ($bill->TRANGTHAI !== 'Chưa thanh toán') {
                $this->fail('invoice', 'Chỉ xử lý hủy hóa đơn đang chờ.');
            }
            if ($user->role === 'customer') {
                DB::table('gym_fulfillments')->where('invoice_id', $invoice)->update(['cancellation_requested' => true, 'updated_at' => now()]);
            } else {
                abort_unless($user->canManageOrders(), 403);
                $this->assertNoPaymentReview($invoice);
                if ($this->received($invoice) > 0) {
                    $this->fail('invoice', 'Hóa đơn đã nhận tiền cần xử lý hoàn tiền trước, không thể hủy trực tiếp.');
                }
                DB::table('HOADON')->where('MAHD', $invoice)->update(['TRANGTHAI' => 'Hủy']);
                DB::table('gym_fulfillments')->where('invoice_id', $invoice)->update(['state' => 'cancelled', 'updated_at' => now()]);
                $this->portal->invoiceMessage($invoice, 'cancelled', 'Đơn hàng đã được hủy', 'Đơn #'.$invoice.' đã được nhân viên hủy và giải phóng hàng/chỗ.');
            }
            $this->sql->audit('Xử lý yêu cầu hủy', 'HOADON:'.$invoice);
        });
    }

    public function fulfill(int $invoice, User $user, string $state): void
    {
        abort_unless($user->canManageOrders(), 403);
        $this->sql->transaction(function () use ($invoice, $state): void {
            $bill = $this->invoice($invoice);
            $shipping = DB::table('gym_fulfillments')->where('invoice_id', $invoice)->first();
            if (! DB::table('gym_invoice_lines')->where('invoice_id', $invoice)->where('kind', 'product')->exists()) {
                $this->fail('state', 'Hóa đơn dịch vụ không có hàng cần giao nhận.');
            }
            $next = $shipping?->mode === 'delivery' ? ['pending' => 'shipping', 'shipping' => 'delivered'] : ['pending' => 'ready', 'ready' => 'collected'];
            if ($bill->TRANGTHAI !== 'Đã thanh toán' || ! $shipping || ($next[$shipping->state] ?? null) !== $state) {
                $this->fail('state', 'Trạng thái giao nhận không hợp lệ.');
            }
            DB::table('gym_fulfillments')->where('invoice_id', $invoice)->update(['state' => $state, 'updated_at' => now()]);
            $label = ['ready' => 'sẵn sàng nhận tại quầy', 'collected' => 'đã được nhận tại quầy', 'shipping' => 'đang được giao', 'delivered' => 'đã được giao'][$state];
            $this->portal->invoiceMessage($invoice, 'fulfill:'.$state, 'Cập nhật giao nhận', 'Đơn #'.$invoice.' '.$label.'.');
            $this->sql->audit('Giao nhận', 'HOADON:'.$invoice, ['state' => $state]);
        });
    }

    public function invoice(int $id, ?User $user = null): object
    {
        $query = DB::table('HOADON as h')->join('KHACHHANG as k', 'k.MAKH', '=', 'h.MAKH')->leftJoin('gym_fulfillments as f', 'f.invoice_id', '=', 'h.MAHD')->select('h.*', 'k.TENKH', 'k.SDT', 'f.shipping_fee');
        if ($user?->role === 'customer') {
            $query->where('h.MAKH', $this->identities->customer($user));
        } elseif ($user) {
            abort_unless($user->canManageOrders(), 403);
        }
        $invoice = $query->where('h.MAHD', $id)->first();
        abort_unless($invoice, 404);

        return $invoice;
    }

    public function received(int $id): int
    {
        $orders = DB::table('membership_orders')->where('sql_invoice_id', $id)->pluck('id');

        return (int) DB::table('gym_receipts')->where('invoice_id', $id)->sum('amount')
            + (int) DB::table('gym_online_payments')->where('target_type', 'invoice')->where('target_id', $id)->where('status', 'succeeded')->sum('amount')
            + (int) DB::table('order_payments')->whereIn('membership_order_id', $orders)->sum('amount')
            + (int) DB::table('gym_online_payments')->where('target_type', 'membership_order')->whereIn('target_id', $orders)->where('status', 'succeeded')->sum('amount');
    }

    private function assertNoPaymentReview(int $invoice): void
    {
        if (DB::table('gym_online_payments')->where('target_type', 'invoice')->where('target_id', $invoice)->where('status', 'review')->exists()) {
            $this->fail('payment', 'Hóa đơn có giao dịch VNPay đang đối soát; quản lý cần xử lý trước khi thu, chốt hoặc hủy.');
        }
    }

    public function assertInvoiceHasDetails(int $id): void
    {
        if ($this->portal->unreconciledInvoices()->where('h.MAHD', $id)->exists()) {
            $this->fail('invoice', 'Hóa đơn thiếu hoặc chưa khớp chi tiết/tổng tiền. Cần đối chiếu chứng từ trước khi thu tiền, xác nhận hoặc ký.');
        }
    }

    private function assertBeforeDeadline(int $id): void
    {
        $deadline = DB::table('gym_fulfillments')->where('invoice_id', $id)->value('expires_at');
        if ($deadline && now()->gte($deadline) && $this->received($id) === 0) {
            $this->fail('invoice', 'Đơn đã hết hạn thanh toán. Vui lòng đặt đơn mới; không thu tiền vào đơn này.');
        }
    }

    public function assertPackagePeriod(int $customer, string $start, int $months): CarbonImmutable
    {
        $date = CarbonImmutable::parse($start)->startOfDay();
        if ($date->lt(now()->startOfDay())) {
            $this->fail('start_date', 'Ngày bắt đầu đã qua; hãy chốt lại ngày với khách.');
        }
        $expiry = $this->period->expiresOn($date, $months);
        if (DB::table('DANGKYGOITAP')->where('MAKH', $customer)->where('TRANGTHAI', 'Còn hiệu lực')->where('NGAYBATDAU', '<', $expiry)->where('NGAYKETTHUC', '>=', $date)->exists()) {
            $this->fail('start_date', 'Thời hạn trùng với gói đang có của khách.');
        }

        return $expiry;
    }

    private function assertClassAvailable(int $customer, object $class, ?int $exceptInvoice = null): void
    {
        foreach (DB::table('LICHLOP')->where('MALOP', $class->item_id)->get() as $session) {
            $this->training->assertCustomerFree($customer, CarbonImmutable::parse($session->GIOBATDAU), CarbonImmutable::parse($session->GIOKETTHUC), $class->item_id);
        }
        if (CarbonImmutable::parse($class->NGAYBATDAU)->startOfDay()->lt(now()->startOfDay())) {
            $this->fail('class', 'Lớp đã bắt đầu, không nhận đăng ký mới.');
        }
        $active = DB::table('DANGKYLOP')->where('MALOP', $class->item_id)->where('TRANGTHAI', 'Còn hiệu lực');
        if ((clone $active)->where('MAKH', $customer)->exists()) {
            $this->fail('class', 'Bạn đã đăng ký lớp này.');
        }
        $pending = DB::table('gym_invoice_lines as l')->join('HOADON as h', 'h.MAHD', '=', 'l.invoice_id')->where('l.kind', 'class')->where('l.item_id', $class->item_id)->where('h.TRANGTHAI', 'Chưa thanh toán')->when($exceptInvoice, fn ($q) => $q->where('h.MAHD', '!=', $exceptInvoice));
        if ((clone $pending)->where('h.MAKH', $customer)->exists()) {
            $this->fail('class', 'Bạn còn đơn đăng ký lớp đang chờ.');
        }
        if ($class->SISOTOIDA && $active->count() + $pending->count() >= (int) $class->SISOTOIDA) {
            $this->fail('class', 'Lớp đã đủ chỗ.');
        }
    }

    private function cacheMembership(object $bill, object $line, int $registration, string $start, CarbonImmutable $expiry, ?User $staff): void
    {
        $user = User::query()->where('sql_customer_id', $bill->MAKH)->first();
        $package = GymPackage::query()->where('sql_package_id', $line->item_id)->first();
        if (! $user || ! $package) {
            return;
        }
        $method = DB::table('gym_fulfillments')->where('invoice_id', $bill->MAHD)->value('payment_method');
        $order = MembershipOrder::query()->forceCreate(['user_id' => $user->id, 'gym_package_id' => $package->id, 'amount' => $line->unit_price, 'duration_months' => json_decode($line->details, true)['months'], 'start_date' => $start, 'payment_method' => $method, 'status' => 'paid', 'paid_at' => now(), 'confirmed_by_user_id' => $staff?->id, 'sql_invoice_id' => $bill->MAHD]);
        Membership::query()->forceCreate(['membership_order_id' => $order->id, 'user_id' => $user->id, 'gym_package_id' => $package->id, 'starts_on' => $start, 'expires_on' => $expiry->toDateString(), 'sql_membership_id' => $registration]);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
