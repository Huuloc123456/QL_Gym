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

    private function contract(User $customer, User $trainer, int $sessions = 2, int $id = 9001): int
    {
        DB::table('DANGKYPT')->insert(['MADKPT' => $id, 'MAKH' => $customer->sql_customer_id, 'MANV' => $trainer->sql_employee_id, 'SOBUOI' => $sessions, 'GIAMOIBUOI' => 100000, 'NGAYDANGKY' => now(), 'TRANGTHAI' => 'Còn hiệu lực']);

        return $id;
    }

    private function time(string $start = '09:00', string $end = '10:00'): array
    {
        return ['date' => '2027-01-11', 'start' => $start, 'end' => $end];
    }

    public function test_pt_booking_enforces_ownership_overlap_and_session_limit(): void
    {
        $this->travelTo('2027-01-10 08:00');
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $trainer = User::factory()->employee()->create();
        $contract = $this->contract($customer, $trainer, 1);
        $this->actingAs($other)->post(route('gym.pt.book', $contract), $this->time())->assertNotFound();
        $this->actingAs($customer)->post(route('gym.pt.book', $contract), $this->time())->assertRedirect()->assertSessionHasNoErrors();
        $second = $this->contract($other, $trainer, 2, 9002);
        $this->actingAs($other)->post(route('gym.pt.book', $second), $this->time('09:30', '10:30'))->assertSessionHasErrors('date');
        $this->actingAs($customer)->post(route('gym.pt.book', $contract), $this->time('11:00', '12:00'))->assertSessionHasErrors('contract');
        $this->assertDatabaseCount('LICHTAPPT', 1);
    }

    public function test_completion_requires_assigned_staff_and_elapsed_session_and_is_idempotent(): void
    {
        $this->travelTo('2027-01-10 08:00');
        $customer = User::factory()->create();
        $trainer = User::factory()->employee()->create();
        $other = User::factory()->employee()->create();
        $contract = $this->contract($customer, $trainer, 1);
        $this->actingAs($customer)->post(route('gym.pt.book', $contract), $this->time())->assertRedirect()->assertSessionHasNoErrors();
        $session = DB::table('LICHTAPPT')->where('MADKPT', $contract)->value('MALICHPT');
        $this->actingAs($other)->post(route('gym.sessions.finish', ['pt', $session]))->assertForbidden();
        $this->actingAs($trainer)->post(route('gym.sessions.finish', ['pt', $session]))->assertSessionHasErrors('session');
        $this->travelTo('2027-01-11 10:01');
        $this->post(route('gym.sessions.finish', ['pt', $session]))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('gym.sessions.finish', ['pt', $session]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('DANGKYPT', ['MADKPT' => $contract, 'TRANGTHAI' => 'Kết thúc']);
        $this->assertDatabaseHas('LICHTAPPT', ['MALICHPT' => $session, 'TRANGTHAI' => 'Đã tập']);
    }

    public function test_class_requires_specialty_and_reserves_capacity_and_customer_time(): void
    {
        $this->travelTo('2027-01-10 08:00');
        $manager = User::factory()->manager()->create();
        $trainer = User::factory()->employee()->create();
        $customer = User::factory()->create();
        $other = User::factory()->create();
        DB::table('LOPHOC')->insert(['MALOP' => 9001, 'TENLOP' => 'Lớp kiểm thử', 'MACM' => 1, 'HOCPHI' => 100000, 'NGAYBATDAU' => '2027-01-11', 'NGAYKETTHUC' => '2027-02-11', 'SOBUOI' => 2, 'SISOTOIDA' => 1]);
        $data = $this->time() + ['employee' => $trainer->sql_employee_id];
        $this->actingAs($manager)->post(route('gym.class.session', 9001), $data)->assertSessionHasErrors('employee');
        DB::table('NHANVIEN_CHUYENMON')->insert(['MANV' => $trainer->sql_employee_id, 'MACM' => 1]);
        $this->post(route('gym.class.session', 9001), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'class', 'item' => 9001, 'quantity' => 1])->assertRedirect();
        $this->post(route('gym.checkout'), ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($other)->post(route('gym.cart.add'), ['kind' => 'class', 'item' => 9001, 'quantity' => 1])->assertRedirect();
        $this->post(route('gym.checkout'), ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash'])->assertSessionHasErrors('class');
        $pt = User::factory()->employee()->create();
        $contract = $this->contract($customer, $pt);
        $this->actingAs($customer)->post(route('gym.pt.book', $contract), $this->time())->assertSessionHasErrors('date');
        $this->assertDatabaseHas('LICHLOP', ['MALOP' => 9001, 'MANV' => $trainer->sql_employee_id]);
    }

    public function test_customer_can_remove_future_pt_session_and_rebook_but_cannot_cancel_contract(): void
    {
        $this->travelTo('2027-01-10 08:00');
        $customer = User::factory()->create();
        $trainer = User::factory()->employee()->create();
        $contract = $this->contract($customer, $trainer, 1);
        $this->actingAs($customer)->post(route('gym.pt.book', $contract), $this->time())->assertRedirect()->assertSessionHasNoErrors();
        $session = DB::table('LICHTAPPT')->where('MADKPT', $contract)->value('MALICHPT');
        $this->delete(route('gym.sessions.remove', ['pt', $session]))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('gym.registration.end', ['pt', $contract]), ['reason' => 'Yêu cầu hủy'])->assertForbidden();
        $this->post(route('gym.pt.book', $contract), $this->time('11:00', '12:00'))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('LICHTAPPT')->where('MADKPT', $contract)->count());
    }
}
