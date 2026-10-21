<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GymTrainingService
{
    public function __construct(private OriginalGymSql $sql, private GymIdentityService $identities, private GymPortalService $portal) {}

    public function bookPt(User $user, int $contract, array $data): int
    {
        return $this->sql->transaction(function () use ($user, $contract, $data): int {
            $registration = $this->ptContract($user, $contract);
            if ($registration->TRANGTHAI !== 'Còn hiệu lực') {
                $this->fail('contract', 'Đăng ký PT không còn hiệu lực.');
            }
            if (DB::table('LICHTAPPT')->where('MADKPT', $contract)->count() >= (int) $registration->SOBUOI) {
                $this->fail('contract', 'Tất cả buổi trong hợp đồng đã tập hoặc đã được xếp lịch.');
            }
            [$start, $end] = $this->times($data);
            $this->assertInstructorFree((int) $registration->MANV, $start, $end);
            $this->assertCustomerFree((int) $registration->MAKH, $start, $end);
            $id = $this->sql->nextId('LICHTAPPT');
            DB::table('LICHTAPPT')->insert(['MALICHPT' => $id, 'MADKPT' => $contract, 'NGAYTAP' => $start->toDateString(), 'GIOBATDAU' => $start, 'GIOKETTHUC' => $end, 'TRANGTHAI' => 'Chưa tập']);
            $this->portal->trainingMessage((int) $registration->MAKH, (int) $registration->MANV, 'pt-booked:'.$id.':'.Str::uuid(), 'Đã xếp lịch PT', 'Buổi PT đã được xếp lúc '.$start->format('H:i d/m/Y').'. Xem lịch để kiểm tra hoặc hủy trước khi bắt đầu.');
            $this->sql->audit('Xếp lịch PT', 'LICHTAPPT:'.$id);

            return $id;
        });
    }

    public function classSession(User $user, int $class, array $data): void
    {
        abort_unless($user->role === 'manager', 403);
        $this->sql->transaction(function () use ($class, $data): void {
            $course = DB::table('LOPHOC')->where('MALOP', $class)->first();
            abort_unless($course, 404);
            [$start, $end] = $this->times($data);
            if ($start->toDateString() < substr($course->NGAYBATDAU, 0, 10) || $end->toDateString() > substr($course->NGAYKETTHUC, 0, 10)) {
                $this->fail('date', 'Buổi học phải nằm trong thời gian khóa học.');
            }
            if (! DB::table('NHANVIEN_CHUYENMON')->where('MANV', $data['employee'])->where('MACM', $course->MACM)->exists()) {
                $this->fail('employee', 'HLV chưa được gán chuyên môn của lớp.');
            }
            if (DB::table('LICHLOP')->where('MALOP', $class)->count() >= (int) $course->SOBUOI) {
                $this->fail('class', 'Đã xếp đủ số buổi của khóa học.');
            }
            if ($this->overlaps(DB::table('LICHLOP')->where('MALOP', $class), $start, $end)->exists()) {
                $this->fail('date', 'Lớp đã có buổi học trong khung giờ này.');
            }
            $this->assertInstructorFree((int) $data['employee'], $start, $end);
            $customers = DB::table('DANGKYLOP')->where('MALOP', $class)->where('TRANGTHAI', 'Còn hiệu lực')->pluck('MAKH')->merge(DB::table('gym_invoice_lines as l')->join('HOADON as h', 'h.MAHD', '=', 'l.invoice_id')->where('l.kind', 'class')->where('l.item_id', $class)->where('h.TRANGTHAI', 'Chưa thanh toán')->pluck('h.MAKH'))->unique();
            foreach ($customers as $customer) {
                $this->assertCustomerFree((int) $customer, $start, $end, $class);
            }
            $id = $this->sql->nextId('LICHLOP');
            DB::table('LICHLOP')->insert(['MALICHLOP' => $id, 'MALOP' => $class, 'MANV' => $data['employee'], 'NGAYHOC' => $start->toDateString(), 'GIOBATDAU' => $start, 'GIOKETTHUC' => $end, 'TRANGTHAI' => 'Chưa diễn ra']);
            $eventKey = 'class-booked:'.$id.':'.Str::uuid();
            foreach ($customers->push(0)->unique() as $customer) {
                $this->portal->trainingMessage((int) $customer, (int) $data['employee'], $eventKey, 'Đã xếp lịch lớp học', $course->TENLOP.' được xếp lúc '.$start->format('H:i d/m/Y').'.');
            }
            $this->sql->audit('Xếp lịch lớp', 'LICHLOP:'.$id);
        });
    }

    public function finish(User $user, string $kind, int $id): void
    {
        abort_unless(in_array($user->role, ['employee', 'manager'], true), 403);
        $this->sql->transaction(function () use ($user, $kind, $id): void {
            $table = $kind === 'pt' ? 'LICHTAPPT' : 'LICHLOP';
            $key = OriginalGymSql::TABLES[$table];
            $query = DB::table($table);
            if ($kind === 'pt') {
                $query->join('DANGKYPT', 'DANGKYPT.MADKPT', '=', 'LICHTAPPT.MADKPT');
            }
            $session = $query->where($key, $id)->first();
            abort_unless($session, 404);
            abort_unless($user->role === 'manager' || (int) $session->MANV === $this->identities->employee($user), 403);
            if (CarbonImmutable::parse($session->GIOKETTHUC)->isFuture()) {
                $this->fail('session', 'Chỉ xác nhận hoàn thành khi đã qua giờ kết thúc.');
            }
            DB::table($table)->where($key, $id)->update(['TRANGTHAI' => $kind === 'pt' ? 'Đã tập' : 'Đã diễn ra']);
            if ($kind === 'pt') {
                $done = DB::table('LICHTAPPT')->where('MADKPT', $session->MADKPT)->where('TRANGTHAI', 'Đã tập')->count();
                if ($done >= (int) $session->SOBUOI) {
                    DB::table('DANGKYPT')->where('MADKPT', $session->MADKPT)->update(['TRANGTHAI' => 'Kết thúc']);
                }
            } else {
                $course = DB::table('LOPHOC')->where('MALOP', $session->MALOP)->first();
                if (DB::table('LICHLOP')->where('MALOP', $session->MALOP)->where('TRANGTHAI', 'Đã diễn ra')->count() >= (int) $course->SOBUOI) {
                    DB::table('DANGKYLOP')->where('MALOP', $session->MALOP)->where('TRANGTHAI', 'Còn hiệu lực')->update(['TRANGTHAI' => 'Kết thúc']);
                }
            }
            $this->sql->audit('Hoàn thành buổi tập', $table.':'.$id);
        });
    }

    public function removeSession(User $user, string $kind, int $id): void
    {
        $this->sql->transaction(function () use ($user, $kind, $id): void {
            if ($kind === 'pt') {
                $session = DB::table('LICHTAPPT')->where('MALICHPT', $id)->first();
                abort_unless($session, 404);
                $contract = $this->ptContract($user, (int) $session->MADKPT);
                if ($session->TRANGTHAI !== 'Chưa tập' || CarbonImmutable::parse($session->GIOBATDAU)->isPast()) {
                    $this->fail('session', 'Chỉ hủy lịch PT chưa bắt đầu.');
                }
                DB::table('LICHTAPPT')->where('MALICHPT', $id)->delete();
                $this->portal->trainingMessage((int) $contract->MAKH, (int) $contract->MANV, 'pt-cancelled:'.$id.':'.Str::uuid(), 'Buổi PT đã được hủy', 'Lịch PT lúc '.$session->GIOBATDAU.' đã hủy trước khi bắt đầu. Số buổi chưa sử dụng có thể xếp lại.');
            } else {
                abort_unless($user->role === 'manager', 403);
                $session = DB::table('LICHLOP')->where('MALICHLOP', $id)->first();
                abort_unless($session, 404);
                if ($session->TRANGTHAI !== 'Chưa diễn ra' || CarbonImmutable::parse($session->GIOBATDAU)->isPast()) {
                    $this->fail('session', 'Chỉ hủy lịch lớp chưa bắt đầu.');
                }
                DB::table('LICHLOP')->where('MALICHLOP', $id)->delete();
                $customers = DB::table('DANGKYLOP')->where('MALOP', $session->MALOP)->where('TRANGTHAI', 'Còn hiệu lực')->pluck('MAKH')->push(0)->unique();
                $eventKey = 'class-cancelled:'.$id.':'.Str::uuid();
                foreach ($customers as $customer) {
                    $this->portal->trainingMessage((int) $customer, (int) $session->MANV, $eventKey, 'Buổi học đã được hủy', 'Buổi lớp lúc '.$session->GIOBATDAU.' đã hủy. Vui lòng theo dõi lịch thay thế từ quản lý.');
                }
            }
            $this->sql->audit('Hủy lịch chưa bắt đầu', $kind.':'.$id);
        });
    }

    public function ptContract(User $user, int $id): object
    {
        $query = DB::table('DANGKYPT');
        if ($user->role === 'customer') {
            $query->where('MAKH', $this->identities->customer($user));
        } elseif ($user->role !== 'manager' && ! $user->canManageOrders()) {
            $query->where('MANV', $this->identities->employee($user));
        }
        $registration = $query->where('MADKPT', $id)->first();
        abort_unless($registration, 404);

        return $registration;
    }

    public function endRegistration(User $user, string $kind, int $id, string $reason): void
    {
        abort_unless($user->role === 'manager', 403);
        $this->sql->transaction(function () use ($kind, $id, $reason): void {
            [$table, $key, $status] = match ($kind) {
                'package' => ['DANGKYGOITAP', 'MADKGT', 'Đã hủy'],
                'class' => ['DANGKYLOP', 'MADKLOP', 'Hủy'],
                'pt' => ['DANGKYPT', 'MADKPT', 'Hủy'],
            };
            abort_unless(DB::table($table)->where($key, $id)->exists(), 404);
            DB::table($table)->where($key, $id)->update(['TRANGTHAI' => $status]);
            if ($kind === 'pt') {
                DB::table('LICHTAPPT')->where('MADKPT', $id)->where('TRANGTHAI', 'Chưa tập')->where('GIOBATDAU', '>', now())->delete();
            }
            $this->sql->audit('Chấm dứt đăng ký', $table.':'.$id, ['reason' => $reason]);
        });
    }

    private function times(array $data): array
    {
        $start = CarbonImmutable::parse($data['date'].' '.$data['start']);
        $end = CarbonImmutable::parse($data['date'].' '.$data['end']);
        if ($start->isPast() || $end->lte($start)) {
            $this->fail('date', 'Chọn thời gian tương lai và giờ kết thúc sau giờ bắt đầu.');
        }

        return [$start, $end];
    }

    private function assertInstructorFree(int $employee, CarbonImmutable $start, CarbonImmutable $end): void
    {
        if (User::query()->where('sql_employee_id', $employee)->where('is_active', false)->exists()) {
            $this->fail('employee', 'HLV/PT đã bị khóa tài khoản.');
        }
        $pt = DB::table('LICHTAPPT as s')->join('DANGKYPT as r', 'r.MADKPT', '=', 's.MADKPT')->where('r.MANV', $employee)->where('r.TRANGTHAI', '!=', 'Hủy');
        $classes = DB::table('LICHLOP')->where('MANV', $employee);
        if ($this->overlaps($pt, $start, $end, 's.')->exists() || $this->overlaps($classes, $start, $end)->exists()) {
            $this->fail('date', 'HLV/PT đã có lịch trùng khung giờ.');
        }
    }

    public function assertCustomerFree(int $customer, CarbonImmutable $start, CarbonImmutable $end, ?int $exceptClass = null): void
    {
        $pt = DB::table('LICHTAPPT as s')->join('DANGKYPT as r', 'r.MADKPT', '=', 's.MADKPT')->where('r.MAKH', $customer)->where('r.TRANGTHAI', '!=', 'Hủy');
        $classes = DB::table('LICHLOP as s')->join('DANGKYLOP as r', 'r.MALOP', '=', 's.MALOP')->where('r.MAKH', $customer)->where('r.TRANGTHAI', 'Còn hiệu lực')->when($exceptClass, fn ($q) => $q->where('s.MALOP', '!=', $exceptClass));
        $pending = DB::table('LICHLOP as s')->join('gym_invoice_lines as l', 'l.item_id', '=', 's.MALOP')->join('HOADON as h', 'h.MAHD', '=', 'l.invoice_id')->where('l.kind', 'class')->where('h.MAKH', $customer)->where('h.TRANGTHAI', 'Chưa thanh toán')->when($exceptClass, fn ($q) => $q->where('s.MALOP', '!=', $exceptClass));
        if ($this->overlaps($pt, $start, $end, 's.')->exists() || $this->overlaps($classes, $start, $end, 's.')->exists() || $this->overlaps($pending, $start, $end, 's.')->exists()) {
            $this->fail('date', 'Học viên đã có lịch tập hoặc lớp trùng giờ.');
        }
    }

    private function overlaps(Builder $query, CarbonImmutable $start, CarbonImmutable $end, string $prefix = ''): Builder
    {
        return $query->where($prefix.'GIOBATDAU', '<', $end)->where($prefix.'GIOKETTHUC', '>', $start);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
