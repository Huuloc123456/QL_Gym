<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class GymTrainingTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_rejects_classes_with_overlapping_sessions_in_the_same_cart(): void
    {
        $this->travelTo('2027-01-10 08:00');
        $customer = User::factory()->create();
        $teacher = User::factory()->employee()->create();
        foreach ([9001, 9002] as $id) {
            DB::table('LOPHOC')->insert(['MALOP' => $id, 'TENLOP' => 'Lớp '.$id, 'MACM' => 1, 'HOCPHI' => 100000, 'NGAYBATDAU' => '2027-01-11', 'NGAYKETTHUC' => '2027-02-11', 'SOBUOI' => 2, 'SISOTOIDA' => 10]);
            DB::table('LICHLOP')->insert(['MALICHLOP' => $id, 'MALOP' => $id, 'MANV' => $teacher->sql_employee_id, 'NGAYHOC' => '2027-01-11', 'GIOBATDAU' => '2027-01-11 09:00:00', 'GIOKETTHUC' => '2027-01-11 10:00:00', 'TRANGTHAI' => 'Chưa diễn ra']);
            $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'class', 'item' => $id, 'quantity' => 1])->assertSessionHasNoErrors();
        }

        $this->post(route('gym.checkout'), ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash'])->assertSessionHasErrors('class');
        $this->assertDatabaseMissing('HOADON', ['MAKH' => $customer->sql_customer_id]);
    }

    private function legacyPtContract(User $customer, User $trainer, int $id): int
    {
        DB::table('DANGKYPT')->insert(['MADKPT' => $id, 'MAKH' => $customer->sql_customer_id, 'MANV' => $trainer->sql_employee_id, 'SOBUOI' => 1, 'GIAMOIBUOI' => 100000, 'NGAYDANGKY' => now(), 'TRANGTHAI' => 'Còn hiệu lực']);

        return $id;
    }

    private function classTime(): array
    {
        return ['date' => '2027-01-11', 'start' => '09:00', 'end' => '10:00'];
    }

    public function test_pt_scheduling_is_removed_from_calendar_and_staff_actions(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->create(['name' => 'Khách PT lưu lịch sử']);
        $trainer = User::factory()->employee()->create(['name' => 'Nhân viên lớp lịch sử']);
        $contract = $this->legacyPtContract($customer, $trainer, 9015);
        DB::table('LICHTAPPT')->insert([
            'MALICHPT' => 9015,
            'MADKPT' => $contract,
            'NGAYTAP' => '2027-01-11',
            'GIOBATDAU' => '2027-01-11 09:00:00',
            'GIOKETTHUC' => '2027-01-11 10:00:00',
            'TRANGTHAI' => 'Chưa tập',
        ]);

        $this->actingAs($manager)->get(route('gym.calendar'))
            ->assertOk()
            ->assertDontSee('Đăng ký PT')
            ->assertDontSee('Các buổi PT')
            ->assertDontSee('Khách PT lưu lịch sử')
            ->assertViewHas('calendar', fn ($calendar) => $calendar['events']->isEmpty());

        $this->delete('/lich-tap/pt/9015')->assertNotFound();
        $this->post('/quan-ly/dang-ky/pt/9015/huy', ['reason' => 'Ngừng nghiệp vụ PT'])->assertNotFound();
        $this->assertDatabaseHas('LICHTAPPT', ['MALICHPT' => 9015, 'TRANGTHAI' => 'Chưa tập']);
    }

    public function test_customers_cannot_open_the_removed_public_pt_routes(): void
    {
        $this->get('/huan-luyen-vien')->assertNotFound();
        $this->post('/huan-luyen-vien/24/dang-ky', [])->assertNotFound();
        $this->actingAs(User::factory()->create())->post('/gio-hang', [
            'kind' => 'pt',
            'item' => 24,
            'quantity' => 1,
        ])->assertSessionHasErrors('kind');
    }

    public function test_class_registration_and_calendar_still_work_without_pt_overlap_rules(): void
    {
        $this->travelTo('2027-01-10 08:00');
        $manager = User::factory()->manager()->create();
        $trainer = User::factory()->employee()->create();
        $customer = User::factory()->create();
        $other = User::factory()->create();
        DB::table('LOPHOC')->insert(['MALOP' => 9001, 'TENLOP' => 'Lớp kiểm thử', 'MACM' => 1, 'HOCPHI' => 100000, 'NGAYBATDAU' => '2027-01-11', 'NGAYKETTHUC' => '2027-02-11', 'SOBUOI' => 2, 'SISOTOIDA' => 1]);
        $data = $this->classTime() + ['employee' => $trainer->sql_employee_id];
        $this->actingAs($manager)->post(route('gym.class.session', 9001), $data)->assertSessionHasErrors('employee');
        DB::table('NHANVIEN_CHUYENMON')->insert(['MANV' => $trainer->sql_employee_id, 'MACM' => 1]);
        $this->post(route('gym.class.session', 9001), $data)->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'class', 'item' => 9001, 'quantity' => 1])->assertRedirect();
        $this->post(route('gym.checkout'), ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($other)->post(route('gym.cart.add'), ['kind' => 'class', 'item' => 9001, 'quantity' => 1])->assertRedirect();
        $this->post(route('gym.checkout'), ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash'])->assertSessionHasErrors('class');

        $this->actingAs($manager)->get(route('gym.calendar'))
            ->assertOk()
            ->assertSee('Lớp kiểm thử')
            ->assertSee('Đăng ký lớp học')
            ->assertSee('Lịch lớp học')
            ->assertDontSee('Đăng ký PT');
        $this->assertDatabaseHas('LICHLOP', ['MALOP' => 9001, 'MANV' => $trainer->sql_employee_id]);
    }

    public function test_manager_cannot_delete_a_class_reserved_by_a_pending_invoice(): void
    {
        $this->travelTo('2026-10-27 08:00');
        $customer = User::factory()->create();
        $manager = User::factory()->manager()->create();
        DB::table('LOPHOC')->insert([
            'MALOP' => 9905,
            'TENLOP' => 'Lớp đang chờ QA',
            'MACM' => 1,
            'HOCPHI' => 100000,
            'NGAYBATDAU' => '2027-01-11',
            'NGAYKETTHUC' => '2027-02-11',
            'SOBUOI' => 2,
            'SISOTOIDA' => 10,
        ]);
        $this->actingAs($customer)->post(route('gym.cart.add'), [
            'kind' => 'class', 'item' => 9905, 'quantity' => 1,
        ])->assertSessionHasNoErrors();
        $this->post(route('gym.checkout'), [
            'token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $this->actingAs($manager)
            ->delete(route('gym.admin.destroy', ['classes', 9905]))
            ->assertSessionHasErrors('record');

        $this->assertDatabaseHas('LOPHOC', ['MALOP' => 9905, 'TENLOP' => 'Lớp đang chờ QA']);
        $this->assertDatabaseHas('gym_invoice_lines', ['kind' => 'class', 'item_id' => 9905]);
    }
}
