<?php

namespace Database\Seeders;

use App\Http\Controllers\TrialRequestController;
use App\Models\CheckIn;
use App\Models\GymPackage;
use App\Models\GymSetting;
use App\Models\Membership;
use App\Models\TrialRequest;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\GymCommerceService;
use App\Services\GymIdentityService;
use App\Services\GymTrainingService;
use App\Services\MembershipPeriod;
use App\Services\OriginalGymSql;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GymDemoScenarioSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Chỉ tạo dữ liệu demo trên local hoặc testing.');
        }
        $manifest = app(OriginalGymSql::class)->transaction(function (): array {
            $accounts = [];
            $definitions = [
                'demo_quanly' => ['Quản lý', 'manager'], 'demo_nhanvien' => ['Lễ tân', 'employee'],
                'demo_kho' => ['Kho, không thu tiền', 'employee'], 'demo_pt' => ['PT', 'employee'],
                'demo_khach' => ['Khách mua mới A', 'customer'], 'demo_khach_b' => ['Khách B', 'customer'],
                'demo_hoivien' => ['Hội viên đang hiệu lực', 'customer'], 'demo_hethan' => ['Hội viên hết hạn', 'customer'],
                'demo_tuonglai' => ['Gói bắt đầu tương lai', 'customer'], 'demo_khoa' => ['Tài khoản bị khóa', 'customer'],
                'demo_tapthu' => ['Khách tập thử', 'customer'], 'demo_dangtap' => ['Khách đang check-in', 'customer'],
            ];
            foreach ($definitions as $username => [$label, $role]) {
                $accounts[$username] = User::query()->firstOrCreate(['username' => $username], [
                    'name' => 'DEMO - '.$label, 'email' => $username.'@example.test',
                    'phone' => '099900'.str_pad((string) (count($accounts) + 101), 4, '0', STR_PAD_LEFT),
                    'birth_date' => '1995-01-01', 'role' => $role, 'password' => 'DemoGym@2026',
                    'is_active' => $username !== 'demo_khoa',
                    'can_manage_orders' => $username === 'demo_nhanvien',
                    'can_manage_trial_requests' => $username === 'demo_nhanvien',
                    'can_manage_check_ins' => $username === 'demo_nhanvien',
                ]);
                if ($role === 'customer' && $accounts[$username]->wasRecentlyCreated) {
                    DB::table('KHACHHANG')->where('MAKH', $accounts[$username]->sql_customer_id)
                        ->update(['MALOAIKH' => DB::table('LOAIKHACHHANG')->where('MUCGIAM', 10)->value('MALOAIKH')]);
                }
            }
            $category = $this->record('LOAISANPHAM', 'MALOAISP', 'TENLOAISP', 'DEMO - Phụ kiện', []);
            $products = [
                'manual' => $this->record('SANPHAM', 'MASP', 'TENSP', 'DEMO Bình nước', ['MALOAISP' => $category, 'DONGIA' => 100000, 'GIAKHUYENMAI' => 80000, 'SOLUONGTON' => 30, 'HANG' => 'DEMO', 'XUATXU' => 'Việt Nam', 'MOTACHUNG' => 'Dữ liệu kiểm thử, không giao hàng thật.']),
                'history' => $this->record('SANPHAM', 'MASP', 'TENSP', 'DEMO Khăn tập', ['MALOAISP' => $category, 'DONGIA' => 100000, 'SOLUONGTON' => 20, 'HANG' => 'DEMO']),
                'held' => $this->record('SANPHAM', 'MASP', 'TENSP', 'DEMO Dây kháng lực giữ chỗ', ['MALOAISP' => $category, 'DONGIA' => 100000, 'SOLUONGTON' => 1, 'HANG' => 'DEMO']),
            ];
            $package = GymPackage::query()->firstOrCreate(['name' => 'DEMO Gói 1 tháng'], ['description' => 'Gói dùng cho kịch bản test.', 'price' => 300000, 'duration_months' => 1, 'is_active' => true]);
            $trialPackage = GymPackage::query()->firstOrCreate(['name' => 'DEMO Gói 3 tháng'], ['description' => 'Trả phí 3 tháng; khi duyệt thử, procedure gốc dùng 3 ngày.', 'price' => 800000, 'duration_months' => 3, 'is_active' => true]);
            $specialty = $this->record('CHUYENMON', 'MACM', 'TENCHUYENMON', 'DEMO Thể lực', ['MOTA' => 'Chuyên môn mẫu cho lớp và PT.']);
            $pt = $accounts['demo_pt']->sql_employee_id;
            DB::table('NHANVIEN')->where('MANV', $pt)->update(['MACHUCVU' => DB::table('CHUCVU')->where('TENCHUCVU', 'PT')->value('MACHUCVU')]);
            DB::table('NHANVIEN_CHUYENMON')->insertOrIgnore(['MANV' => $pt, 'MACM' => $specialty]);
            DB::table('gym_pt_rates')->insertOrIgnore(['employee_id' => $pt, 'price' => 100000]);
            $day = CarbonImmutable::today(config('app.timezone'));
            $class = $this->record('LOPHOC', 'MALOP', 'TENLOP', 'DEMO Lớp thể lực', ['MACM' => $specialty, 'HOCPHI' => 200000, 'NGAYBATDAU' => $day->addDays(2), 'NGAYKETTHUC' => $day->addDays(9), 'SOBUOI' => 2, 'SISOTOIDA' => 3]);
            $smallClass = $this->record('LOPHOC', 'MALOP', 'TENLOP', 'DEMO Lớp một chỗ', ['MACM' => $specialty, 'HOCPHI' => 200000, 'NGAYBATDAU' => $day->addDays(3), 'NGAYKETTHUC' => $day->addDays(9), 'SOBUOI' => 1, 'SISOTOIDA' => 1]);
            if (! DB::table('LICHLOP')->where('MALOP', $class)->exists()) {
                foreach ([2, 4] as $offset) {
                    app(GymTrainingService::class)->classSession($accounts['demo_quanly'], $class, ['employee' => $pt, 'date' => $day->addDays($offset)->toDateString(), 'start' => '09:00', 'end' => '10:00']);
                }
                app(GymTrainingService::class)->classSession($accounts['demo_quanly'], $smallClass, ['employee' => $pt, 'date' => $day->addDays(3)->toDateString(), 'start' => '09:00', 'end' => '10:00']);
            }
            foreach (['demo_khach', 'demo_khach_b', 'demo_hoivien'] as $username) {
                $this->address($accounts[$username]);
            }
            if (! DB::table('gym_fulfillments')->where('checkout_token', $this->token('active-package'))->exists()) {
                GymSetting::current()->update(['shipping_fee' => 30000, 'email_enabled' => false]);
            }
            $staff = $accounts['demo_nhanvien'];
            $active = $accounts['demo_hoivien'];
            $invoices = [];
            $invoices['active_package'] = $this->invoice('active-package', $active, [['package', $package->sql_package_id, 1]], $staff, confirm: true);
            $invoices['expired_package'] = $this->invoice('expired-package', $accounts['demo_hethan'], [['package', $package->sql_package_id, 1]], $staff, confirm: true);
            $expired = Membership::query()->whereHas('order', fn ($q) => $q->where('sql_invoice_id', $invoices['expired_package']))->firstOrFail();
            if (! $expired->expires_on->isPast()) {
                $start = $day->subMonthsNoOverflow(2);
                $end = app(MembershipPeriod::class)->expiresOn($start, 1);
                $expired->update(['starts_on' => $start->toDateString(), 'expires_on' => $end->toDateString()]);
                DB::table('DANGKYGOITAP')->where('MADKGT', $expired->sql_membership_id)->update(['NGAYBATDAU' => $start, 'NGAYKETTHUC' => $end->subDay()->endOfDay(), 'TRANGTHAI' => 'Hết hạn']);
                DB::table('membership_orders')->where('id', $expired->membership_order_id)->update(['start_date' => $start, 'created_at' => $start, 'paid_at' => $start]);
                DB::table('gym_invoice_lines')->where('invoice_id', $invoices['expired_package'])->update(['details' => json_encode(['start_date' => $start->toDateString(), 'months' => 1])]);
                $this->backdate($invoices['expired_package'], $start);
            }
            $invoices['future_package'] = $this->invoice('future-package', $accounts['demo_tuonglai'], [['package', $package->sql_package_id, 1]], $staff, confirm: true, start: $day->addDays(7)->toDateString());
            $invoices['inside_package'] = $this->invoice('inside-package', $accounts['demo_dangtap'], [['package', $package->sql_package_id, 1]], $staff, confirm: true);
            $invoices['pending_pickup'] = $this->invoice('pending-pickup', $active, [['product', $products['history'], 1]], $staff);
            $invoices['partial_delivery'] = $this->invoice('partial-delivery', $active, [['product', $products['history'], 1]], $staff, mode: 'delivery', amount: 50000);
            $invoices['paid_delivery'] = $this->invoice('paid-delivery', $active, [['product', $products['history'], 2]], $staff, mode: 'delivery', confirm: true);
            $invoices['collected_pickup'] = $this->invoice('collected-pickup', $active, [['product', $products['history'], 1]], $staff, confirm: true);
            $shop = app(GymCommerceService::class);
            $state = DB::table('gym_fulfillments')->where('invoice_id', $invoices['collected_pickup'])->value('state');
            if ($state === 'pending') {
                $shop->fulfill($invoices['collected_pickup'], $staff, 'ready');
            }
            if (in_array($state, ['pending', 'ready'], true)) {
                $shop->fulfill($invoices['collected_pickup'], $staff, 'collected');
            }
            $invoices['cancelled'] = $this->invoice('cancelled', $accounts['demo_khach_b'], [['product', $products['history'], 1]], $staff);
            if ($shop->invoice($invoices['cancelled'])->TRANGTHAI === 'Chưa thanh toán') {
                $shop->cancel($invoices['cancelled'], $staff);
            }
            $invoices['held_stock'] = $this->invoice('held-stock', $active, [['product', $products['held'], 1]], $staff);
            $invoices['class'] = $this->invoice('class', $active, [['class', $class, 1]], $staff, confirm: true);
            $invoices['pt'] = $this->invoice('pt', $active, [], $staff, confirm: true, pt: ['employee' => $pt, 'sessions' => 2]);
            $contract = DB::table('CHITIETHOADON')->where('MAHD', $invoices['pt'])->value('MADKPT');
            if (! DB::table('LICHTAPPT')->where('MADKPT', $contract)->exists()) {
                $this->backdate($invoices['pt'], $day->subDays(2));
                DB::table('DANGKYPT')->where('MADKPT', $contract)->update(['NGAYDANGKY' => $day->subDays(2)]);
                DB::table('LICHTAPPT')->insert(['MALICHPT' => app(OriginalGymSql::class)->nextId('LICHTAPPT'), 'MADKPT' => $contract, 'NGAYTAP' => $day->subDay(), 'GIOBATDAU' => $day->subDay()->setTime(11, 0), 'GIOKETTHUC' => $day->subDay()->setTime(12, 0), 'TRANGTHAI' => 'Chưa tập']);
                app(GymTrainingService::class)->bookPt($active, $contract, ['date' => $day->addDays(2)->toDateString(), 'start' => '11:00', 'end' => '12:00']);
            }
            $trialUser = $accounts['demo_tapthu'];
            $trial = TrialRequest::query()->firstOrCreate(['phone' => $trialUser->phone], ['name' => $trialUser->name, 'email' => $trialUser->email, 'status' => 'received']);
            if (! $trial->sql_membership_id) {
                $request = Request::create('/staff/yeu-cau-tap-thu/'.$trial->id.'/duyet', 'POST', ['package' => $trialPackage->sql_package_id, 'identity_verified' => 1]);
                $request->setUserResolver(fn () => $staff);
                app(TrialRequestController::class)->approve($request, $trial, app(OriginalGymSql::class), app(GymIdentityService::class));
            }
            TrialRequest::query()->firstOrCreate(['phone' => '0999000901'], ['name' => 'DEMO - Yêu cầu thử chưa xử lý', 'email' => 'trial.pending@example.test', 'status' => 'received']);
            if (! CheckIn::query()->where('user_id', $active->id)->exists()) {
                $membership = Membership::where('user_id', $active->id)->firstOrFail();
                $visit = CheckIn::query()->create(['user_id' => $active->id, 'membership_id' => $membership->id, 'checked_in_at' => now()->subHours(3), 'checked_out_at' => now()->subHours(2), 'closed_by_user_id' => $staff->id]);
                app(GymIdentityService::class)->syncCheckIn($visit);
            }
            if (! CheckIn::query()->where('user_id', $accounts['demo_dangtap']->id)->exists()) {
                app(CheckInService::class)->checkIn($accounts['demo_dangtap']->phone);
            }
            $invoices['review'] = $this->invoice('review', $accounts['demo_khach_b'], [['product', $products['history'], 1]], $staff);
            DB::table('gym_online_payments')->insertOrIgnore(['target_type' => 'invoice', 'target_id' => $invoices['review'], 'user_id' => $accounts['demo_khach_b']->id, 'txn_ref' => 'DEMO-REVIEW-01', 'amount' => $shop->invoice($invoices['review'])->THANHTIEN, 'status' => 'review', 'response_code' => '00', 'review_reason' => 'Dữ liệu DEMO giả lập để test đối soát, không có giao dịch ngân hàng thật.', 'callback_payload' => json_encode(['vnp_ResponseCode' => '00', 'vnp_TransactionStatus' => '00', 'demo' => true]), 'expires_at' => now()->subMinute(), 'created_at' => now(), 'updated_at' => now()]);

            return ['created_on' => substr((string) DB::table('HOADON')->where('MAHD', $invoices['active_package'])->value('NGAYLAP'), 0, 10), 'accounts' => collect($accounts)->map(fn ($user) => ['username' => $user->username, 'name' => $user->name, 'role' => $user->role, 'phone' => $user->phone, 'customer_id' => $user->sql_customer_id, 'employee_id' => $user->sql_employee_id])->all(), 'products' => $products, 'package_id' => $package->id, 'sql_package_id' => $package->sql_package_id, 'class_id' => $class, 'one_seat_class_id' => $smallClass, 'pt_id' => $pt, 'pt_contract_id' => $contract, 'invoices' => $invoices];
        });
        Storage::disk('local')->put('demo/scenario.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->command?->info('Dữ liệu DEMO đã sẵn sàng. Mật khẩu ban đầu: DemoGym@2026. Danh sách mã tại storage/app/private/demo/scenario.json.');
    }

    private function record(string $table, string $key, string $nameField, string $name, array $values): int
    {
        $id = DB::table($table)->where($nameField, $name)->value($key);
        if ($id !== null) {
            return (int) $id;
        }
        $id = app(OriginalGymSql::class)->nextId($table);
        DB::table($table)->insert([$key => $id, $nameField => $name] + $values);

        return $id;
    }

    private function address(User $customer): int
    {
        $query = DB::table('DIACHI')->where('MAKH', $customer->sql_customer_id)->where('DIACHICUTHE', 'DEMO - 01 Phố Kiểm thử, không giao thật');
        $id = $query->value('MADC');
        if ($id) {
            return (int) $id;
        }

        return DB::table('DIACHI')->insertGetId(['MAKH' => $customer->sql_customer_id, 'TINHTHANHPHO' => 'TP Hồ Chí Minh', 'QUANHUYEN' => 'Quận demo', 'PHUONGXA' => 'Phường demo', 'DIACHICUTHE' => 'DEMO - 01 Phố Kiểm thử, không giao thật', 'LADIACHIMACDINH' => ! DB::table('DIACHI')->where('MAKH', $customer->sql_customer_id)->exists(), 'NGAYTHEM' => now()]);
    }

    private function invoice(string $key, User $customer, array $items, User $staff, string $mode = 'pickup', int $amount = 0, bool $confirm = false, ?string $start = null, ?array $pt = null): int
    {
        $token = $this->token($key);
        $existing = DB::table('gym_fulfillments')->where('checkout_token', $token)->value('invoice_id');
        if ($existing) {
            return (int) $existing;
        }
        $shop = app(GymCommerceService::class);
        foreach ($items as [$kind, $id, $quantity]) {
            $shop->add($customer, $kind, $id, $quantity);
        }
        $data = ['token' => $token, 'mode' => $mode, 'payment_method' => 'cash', 'recipient' => 'DEMO | '.$key, 'phone' => $customer->phone, 'start_date' => $start ?? now()->toDateString()];
        if ($mode === 'delivery') {
            $data['address_id'] = $this->address($customer);
        }
        $invoice = $shop->checkout($customer, $data, $pt);
        $received = $confirm ? (int) $shop->invoice($invoice)->THANHTIEN : $amount;
        if ($received > 0) {
            $shop->receive($invoice, $staff, ['amount' => $received, 'method' => 'cash', 'token' => $this->token('receipt-'.$key), 'reference' => 'DEMO-'.$key]);
        }
        if ($confirm) {
            $shop->confirm($invoice, $staff);
        }

        return $invoice;
    }

    private function token(string $key): string
    {
        $hash = hash('sha256', 'the-gym-demo-scenario-v1:'.$key);

        return substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-4'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
    }

    private function backdate(int $invoice, CarbonImmutable $date): void
    {
        DB::table('HOADON')->where('MAHD', $invoice)->update(['NGAYLAP' => $date]);
        DB::table('gym_fulfillments')->where('invoice_id', $invoice)->update(['created_at' => $date, 'paid_at' => $date, 'expires_at' => $date->addDay()]);
        DB::table('gym_receipts')->where('invoice_id', $invoice)->update(['created_at' => $date]);
    }
}
