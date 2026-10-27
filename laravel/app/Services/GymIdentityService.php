<?php

namespace App\Services;

use App\Models\CheckIn;
use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class GymIdentityService
{
    public function __construct(private OriginalGymSql $sql) {}

    public function customer(User $user): int
    {
        abort_unless($user->role === 'customer', 403);

        $id = $this->sql->transaction(function () use ($user): int {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($user->sql_customer_id) {
                return (int) $user->sql_customer_id;
            }
            if (DB::table('KHACHHANG')->where('TENDANGNHAP', $user->username)->exists()) {
                throw ValidationException::withMessages(['username' => 'Tên đăng nhập trùng dữ liệu SQL cũ. Cần quản lý đối chiếu tài khoản trước khi liên kết.']);
            }
            $result = $this->sql->call('SP_KHACHHANGDANGKY', [mb_substr($user->name, 0, 150), $this->gender($user->gender), $user->birth_date?->toDateString(), $user->phone, $user->email, $user->username, $user->password]);
            $id = (int) $result[0][0]->NewMaKH;
            $user->forceFill(['sql_customer_id' => $id])->saveQuietly();

            return $id;
        });
        $user->setAttribute('sql_customer_id', $id);

        return $id;
    }

    public function employee(User $user): int
    {
        abort_unless(in_array($user->role, ['manager', 'employee'], true), 403);

        $id = $this->sql->transaction(function () use ($user): int {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($user->sql_employee_id) {
                return (int) $user->sql_employee_id;
            }
            if (DB::table('NHANVIEN')->where('TENDANGNHAP', $user->username)->exists()) {
                throw ValidationException::withMessages(['username' => 'Tên nhân viên trùng SQL gốc; cần đối chiếu trước khi liên kết.']);
            }
            $id = $this->sql->nextId('NHANVIEN');
            $position = DB::table('CHUCVU')->where('TENCHUCVU', $user->role === 'manager' ? 'Quản lý' : 'Lễ tân')->value('MACHUCVU');
            DB::table('NHANVIEN')->insert(['MANV' => $id, 'MACHUCVU' => $position, 'TENNV' => mb_substr($user->name, 0, 150), 'TENDANGNHAP' => $user->username, 'MATKHAU' => $user->password, 'SDT' => $user->phone, 'NGAYSINH' => $user->birth_date?->toDateString(), 'GIOITINH' => $this->gender($user->gender)]);
            $user->forceFill(['sql_employee_id' => $id])->saveQuietly();

            return $id;
        });
        $user->setAttribute('sql_employee_id', $id);

        return $id;
    }

    public function sync(User $user): void
    {
        $this->sql->transaction(function () use ($user): void {
            if ($user->role === 'customer') {
                $id = $this->customer($user);
                $old = DB::table('KHACHHANG')->where('MAKH', $id)->first();
                if ($old->MATKHAU !== $user->password && $user->email && $old->EMAIL === $user->email) {
                    $this->sql->call('SP_KHOIPHUCMATKHAU', [$user->email, $user->password]);
                }
                DB::table('KHACHHANG')->where('MAKH', $id)->update(['TENKH' => mb_substr($user->name, 0, 150), 'SDT' => $user->phone, 'EMAIL' => $user->email, 'MATKHAU' => $user->password, 'NGAYSINH' => $user->birth_date?->toDateString(), 'GIOITINH' => $this->gender($user->gender)]);
            } else {
                $id = $this->employee($user);
                DB::table('NHANVIEN')->where('MANV', $id)->update(['TENNV' => mb_substr($user->name, 0, 150), 'SDT' => $user->phone, 'MATKHAU' => $user->password, 'NGAYSINH' => $user->birth_date?->toDateString(), 'GIOITINH' => $this->gender($user->gender)]);
            }
        });
    }

    public function importAccount(object $row, string $kind): ?User
    {
        $customer = $kind === 'customer';
        $key = $customer ? 'sql_customer_id' : 'sql_employee_id';
        $id = (int) ($customer ? $row->MAKH : $row->MANV);
        $existing = User::query()->where('username', $row->TENDANGNHAP)->first();
        if ($existing) {
            return (int) $existing->$key === $id ? $existing : null;
        }
        $role = $customer ? 'customer' : (DB::table('CHUCVU')->where('MACHUCVU', $row->MACHUCVU)->value('TENCHUCVU') === 'Quản lý' ? 'manager' : 'employee');
        $user = new User;
        $user->forceFill(['name' => $customer ? $row->TENKH : $row->TENNV, 'username' => $row->TENDANGNHAP, 'email' => $customer ? $row->EMAIL : null, 'phone' => $row->SDT, 'birth_date' => $row->NGAYSINH, 'gender' => $row->GIOITINH === 'Nam' ? 'male' : ($row->GIOITINH === 'Nữ' ? 'female' : null), 'role' => $role, 'password' => $row->MATKHAU, $key => $id])->saveQuietly();

        return $user;
    }

    public function importLogin(string $username, string $password): void
    {
        if (User::query()->where('username', $username)->exists()) {
            return;
        }
        foreach (['KHACHHANG' => 'customer', 'NHANVIEN' => 'employee'] as $table => $kind) {
            $row = DB::table($table)->where('TENDANGNHAP', $username)->first();
            if ($row && Hash::check($password, $row->MATKHAU)) {
                $this->importAccount($row, $kind);

                return;
            }
        }
    }

    public function loginHistory(string $username, bool $success, ?string $ip): void
    {
        $this->sql->transaction(function () use ($username, $success, $ip): void {
            $customer = DB::table('KHACHHANG')->where('TENDANGNHAP', $username)->first();
            if (! $customer) {
                return;
            }
            if ($success) {
                $this->sql->call('sp_KhachHangLogin', [$username, $customer->MATKHAU]);
            }
            DB::table('LOGINHISTORY')->insert(['ID' => $this->sql->nextId('LOGINHISTORY'), 'USERID' => $customer->MAKH, 'IPADDRESS' => $ip, 'LOGINTIME' => now(), 'STATUS' => $success ? 1 : 0]);
        });
    }

    public function package(GymPackage $package): int
    {
        return $this->sql->transaction(function () use ($package): int {
            if ($package->sql_package_id) {
                return (int) $package->sql_package_id;
            }
            $id = $this->sql->nextId('GOITAP');
            DB::table('GOITAP')->insert(['MAGOITAP' => $id, 'TENGOI' => $package->name, 'THOIHAN' => $package->duration_months, 'GIA' => $package->price, 'MOTA' => $package->description]);
            $package->forceFill(['sql_package_id' => $id])->saveQuietly();

            return $id;
        });
    }

    private function gender(?string $gender): ?string
    {
        return match ($gender) {
            'male' => 'Nam', 'female' => 'Nữ', default => null
        };
    }

    public function syncPackage(GymPackage $package): int
    {
        return $this->sql->transaction(function () use ($package): int {
            $id = $this->package($package);
            DB::table('GOITAP')->where('MAGOITAP', $id)->update(['TENGOI' => mb_substr($package->name, 0, 100), 'THOIHAN' => $package->duration_months, 'GIA' => $package->price, 'MOTA' => $package->description]);

            return $id;
        });
    }

    public function recordMembership(Membership $membership, MembershipOrder $order): void
    {
        $this->sql->transaction(function () use ($membership, $order): void {
            if ($membership->sql_membership_id) {
                return;
            }
            $customer = $this->customer($order->user);
            $package = $this->package($order->gymPackage);
            $invoice = $this->sql->nextId('HOADON');
            DB::table('HOADON')->insert(['MAHD' => $invoice, 'MAKH' => $customer, 'NGAYLAP' => $order->paid_at ?? now(config('app.timezone')), 'TONGTIEN' => $order->amount, 'THANHTIEN' => $order->amount, 'GIAMGIA' => 0, 'TRANGTHAI' => 'Đã thanh toán']);
            $registration = $this->sql->nextId('DANGKYGOITAP');
            DB::table('DANGKYGOITAP')->insert(['MADKGT' => $registration, 'MAKH' => $customer, 'MAGOITAP' => $package, 'NGAYDANGKY' => $membership->created_at, 'NGAYBATDAU' => $membership->starts_on, 'NGAYKETTHUC' => $membership->expires_on->copy()->subDay()->endOfDay(), 'TRANGTHAI' => $membership->expires_on->isPast() ? 'Hết hạn' : 'Còn hiệu lực']);
            DB::table('CHITIETHOADON')->insert(['MACTHD' => $this->sql->nextId('CHITIETHOADON'), 'MAHD' => $invoice, 'MADKGT' => $registration, 'SOLUONG' => 1, 'DONGIA' => $order->amount]);
            $membership->forceFill(['sql_membership_id' => $registration])->saveQuietly();
            $order->forceFill(['sql_invoice_id' => $invoice])->saveQuietly();
        });
    }

    public function syncCheckIn(CheckIn $visit): void
    {
        $this->sql->transaction(function () use ($visit): void {
            $data = ['MAKH' => $this->customer($visit->user), 'THOIGIANVAO' => $visit->checked_in_at, 'THOIGIANRA' => $visit->checked_out_at, 'TRANGTHAI' => 'Hợp lệ'];
            if ($visit->sql_checkin_id) {
                DB::table('CHECKIN')->where('MACHECKIN', $visit->sql_checkin_id)->update($data);
            } else {
                $id = $this->sql->nextId('CHECKIN');
                DB::table('CHECKIN')->insert(['MACHECKIN' => $id] + $data);
                $visit->forceFill(['sql_checkin_id' => $id])->saveQuietly();
            }
        });
    }
}
