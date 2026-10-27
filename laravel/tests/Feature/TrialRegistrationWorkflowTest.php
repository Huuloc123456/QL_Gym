<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\TrialRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrialRegistrationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_must_sign_in_before_requesting_a_trial(): void
    {
        $this->post(route('trials.store'), [
            'name' => 'Khách thử', 'phone' => '0901234567', 'email' => 'trial@example.test',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('trial_requests', 0);
    }

    public function test_trial_request_uses_authenticated_customers_identity(): void
    {
        $customer = User::factory()->create(['name' => 'Hội viên thật', 'phone' => '0900000444', 'email' => 'real@example.test']);

        $this->actingAs($customer)->post(route('trials.store'), [
            'name' => 'Người khác', 'phone' => '0901111111', 'email' => 'other@example.test',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('trial_requests', [
            'name' => $customer->name, 'phone' => $customer->phone, 'email' => $customer->email,
        ]);
    }

    public function test_customer_cannot_create_multiple_pending_trial_requests(): void
    {
        $customer = User::factory()->create();
        $input = ['name' => $customer->name, 'phone' => $customer->phone, 'email' => $customer->email];

        $this->actingAs($customer)->post(route('trials.store'), $input)->assertSessionHasNoErrors();
        $this->post(route('trials.store'), $input)->assertSessionHasErrors();
        $this->assertDatabaseCount('trial_requests', 1);
    }

    public function test_customer_can_be_approved_and_check_in_using_a_day_based_trial(): void
    {
        $customer = User::factory()->create(['phone' => '0900000555']);
        $staff = User::factory()->employee()->create();
        $this->actingAs($customer)->post(route('trials.store'), ['desired_date' => today()->toDateString()])->assertSessionHasNoErrors();
        $trial = TrialRequest::query()->where('user_id', $customer->id)->firstOrFail();
        $count = User::query()->count();

        $this->actingAs($staff)->post(route('staff.trial-requests.approve', $trial), [
            'identity_verified' => 1, 'start_date' => today()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->post(route('staff.trial-requests.approve', $trial), [
            'identity_verified' => 1, 'start_date' => today()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame($count, User::query()->count());
        $this->assertDatabaseCount('trial_entitlements', 1);
        $this->assertDatabaseHas('trial_entitlements', ['trial_request_id' => $trial->id, 'user_id' => $customer->id]);
        $this->assertDatabaseHas('gym_messages', ['user_id' => $customer->id, 'title' => 'Đăng ký tập thử đã được duyệt']);
        $this->assertSame(0, DB::table('HOADON')->where('MAKH', $customer->sql_customer_id)->count());
        $this->post(route('staff.checkins.store'), ['member' => $customer->phone])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('check_ins', ['user_id' => $customer->id, 'trial_entitlement_id' => DB::table('trial_entitlements')->value('id')]);
    }

    public function test_guest_visiting_trial_page_is_directed_to_sign_in(): void
    {
        $this->get('/tap-thu')->assertRedirect(route('login'));
    }

    public function test_new_customer_returns_to_trial_form_after_registration_without_sending_a_request(): void
    {
        $this->get('/tap-thu')->assertRedirect(route('login'));
        $this->post(route('register.store'), ['name' => 'Khách mới', 'username' => 'trialnewuser',
            'phone' => '0900000666', 'email' => 'trialnew@example.test',
            'password' => 'TrialPass123!', 'password_confirmation' => 'TrialPass123!',
        ])->assertRedirect(route('trials.create'));
        $this->assertDatabaseCount('trial_requests', 0);
        $this->get(route('trials.create'))->assertOk()->assertSee('Khách mới');
    }

    public function test_member_can_open_trial_page_and_cancel_only_their_pending_request(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($customer)->get('/tap-thu')->assertOk()->assertSee($customer->name);
        $this->post(route('trials.store'), [])->assertSessionHasNoErrors();
        $trial = TrialRequest::query()->where('user_id', $customer->id)->firstOrFail();

        $this->actingAs($other)->post('/tap-thu/'.$trial->id.'/huy')->assertNotFound();
        $this->actingAs($customer)->post('/tap-thu/'.$trial->id.'/huy')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('trial_requests', ['id' => $trial->id, 'status' => 'cancelled']);
    }

    public function test_staff_may_reject_a_pending_request_with_a_reason(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->employee()->create();
        $this->actingAs($customer)->post(route('trials.store'), [])->assertSessionHasNoErrors();
        $trial = TrialRequest::query()->where('user_id', $customer->id)->firstOrFail();

        $this->actingAs($staff)->post('/staff/yeu-cau-tap-thu/'.$trial->id.'/tu-choi', ['reason' => 'Không thể sắp xếp lịch'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('trial_requests', ['id' => $trial->id, 'status' => 'rejected', 'decided_by' => $staff->id]);
        $this->assertDatabaseHas('gym_messages', ['user_id' => $customer->id, 'title' => 'Đăng ký tập thử chưa được duyệt']);
    }

    public function test_staff_can_reschedule_an_unused_trial_without_creating_another_entitlement(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->employee()->create();
        $this->actingAs($customer)->post(route('trials.store'), [])->assertSessionHasNoErrors();
        $trial = TrialRequest::query()->where('user_id', $customer->id)->firstOrFail();
        $this->actingAs($staff)->post(route('staff.trial-requests.approve', $trial), [
            'identity_verified' => 1, 'start_date' => today()->toDateString(),
        ])->assertSessionHasNoErrors();
        $entitlementId = DB::table('trial_entitlements')->value('id');
        $newStart = today()->addDays(2)->toDateString();

        $this->post(route('staff.trial-requests.reschedule', $trial), ['start_date' => $newStart])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('trial_entitlements', 1);
        $this->assertDatabaseHas('trial_entitlements', [
            'id' => $entitlementId,
            'starts_at' => $newStart.' 00:00:00',
            'ends_at' => today()->addDays(3)->toDateString().' 00:00:00',
        ]);
        $this->assertDatabaseHas('trial_requests', ['id' => $trial->id, 'status' => 'approved', 'desired_date' => $newStart]);
        $this->assertDatabaseHas('gym_messages', ['user_id' => $customer->id, 'title' => 'Lịch tập thử đã được đổi']);
    }

    public function test_staff_cannot_reschedule_a_trial_after_the_customer_has_checked_in(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->employee()->create();
        $this->actingAs($customer)->post(route('trials.store'), [])->assertSessionHasNoErrors();
        $trial = TrialRequest::query()->where('user_id', $customer->id)->firstOrFail();
        $this->actingAs($staff)->post(route('staff.trial-requests.approve', $trial), [
            'identity_verified' => 1, 'start_date' => today()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->post(route('staff.checkins.store'), ['member' => $customer->phone])->assertSessionHasNoErrors();

        $this->post(route('staff.trial-requests.reschedule', $trial), ['start_date' => today()->addDay()->toDateString()])
            ->assertForbidden();

        $this->assertDatabaseCount('trial_entitlements', 1);
        $this->assertDatabaseCount('check_ins', 1);
    }

    public function test_revoking_a_trial_keeps_history_and_prevents_future_check_in(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->employee()->create();
        $this->actingAs($customer)->post(route('trials.store'), [])->assertSessionHasNoErrors();
        $trial = TrialRequest::query()->where('user_id', $customer->id)->firstOrFail();
        $this->actingAs($staff)->post(route('staff.trial-requests.approve', $trial), [
            'identity_verified' => 1, 'start_date' => today()->toDateString(),
        ])->assertSessionHasNoErrors();
        $entitlementId = DB::table('trial_entitlements')->value('id');

        $this->post(route('staff.trial-requests.revoke', $trial), ['reason' => 'Đã xác minh đăng ký nhầm tài khoản'])
            ->assertSessionHasNoErrors();

        $this->post(route('staff.checkins.store'), ['member' => $customer->phone])->assertSessionHasErrors('member');
        $this->assertDatabaseCount('trial_entitlements', 1);
        $this->assertDatabaseHas('trial_entitlements', [
            'id' => $entitlementId,
            'revoked_by' => $staff->id,
            'revocation_reason' => 'Đã xác minh đăng ký nhầm tài khoản',
        ]);
        $this->assertDatabaseHas('gym_messages', ['user_id' => $customer->id, 'title' => 'Quyền tập thử đã bị thu hồi']);
    }

    public function test_customer_with_active_or_upcoming_paid_membership_cannot_request_a_trial(): void
    {
        $customer = User::factory()->create();
        Membership::factory()->for($customer)->create([
            'starts_on' => today()->addDay(), 'expires_on' => today()->addMonth(),
        ]);

        $this->actingAs($customer)->post(route('trials.store'), [])->assertSessionHasErrors('trial');
        $this->assertDatabaseCount('trial_requests', 0);
    }

    public function test_manager_can_link_a_legacy_trial_lead_only_to_an_active_account_with_a_matching_phone(): void
    {
        $customer = User::factory()->create(['username' => 'legacy_customer', 'phone' => '+84 900 111 222']);
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $legacy = TrialRequest::factory()->create(['phone' => '0900111222', 'status' => 'contacted']);

        $this->actingAs($employee)->post(route('staff.trial-requests.link-account', $legacy), [
            'username' => $customer->username,
            'identity_verified' => 1,
        ])->assertForbidden();
        $this->get(route('staff.trial-requests.index'))
            ->assertOk()
            ->assertDontSee(route('staff.trial-requests.link-account', $legacy), false);
        $this->actingAs($manager)->get(route('staff.trial-requests.index'))
            ->assertOk()
            ->assertSee(route('staff.trial-requests.link-account', $legacy), false);

        $this->actingAs($manager)->post(route('staff.trial-requests.link-account', $legacy), [
            'username' => $customer->username,
            'phone_confirmed' => '0901111111',
            'identity_verified' => 1,
        ])->assertSessionHasErrors('trial');
        $this->assertDatabaseHas('trial_requests', ['id' => $legacy->id, 'user_id' => null, 'status' => 'contacted']);

        $this->post(route('staff.trial-requests.link-account', $legacy), [
            'username' => $customer->username,
            'phone_confirmed' => $legacy->phone,
            'identity_verified' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('trial_requests', [
            'id' => $legacy->id,
            'user_id' => $customer->id,
            'status' => 'pending',
            'desired_date' => today()->toDateString(),
            'handled_by' => $manager->id,
        ]);
        $this->post(route('staff.trial-requests.approve', $legacy), [
            'identity_verified' => 1,
            'start_date' => today()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('trial_entitlements', ['trial_request_id' => $legacy->id, 'user_id' => $customer->id]);
    }

    public function test_approved_trial_expires_at_end_of_its_configured_day(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->employee()->create();
        $this->actingAs($customer)->post(route('trials.store'), [])->assertSessionHasNoErrors();
        $trial = TrialRequest::query()->where('user_id', $customer->id)->firstOrFail();
        $this->actingAs($staff)->post(route('staff.trial-requests.approve', $trial), [
            'identity_verified' => 1, 'start_date' => today()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->travelTo(now()->startOfDay()->addDay());
        $this->post(route('staff.checkins.store'), ['member' => $customer->phone])->assertSessionHasErrors('member');
        $this->assertDatabaseCount('check_ins', 0);
    }

    public function test_staff_cannot_approve_trial_if_customer_has_bought_an_upcoming_membership(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->employee()->create();
        $this->actingAs($customer)->post(route('trials.store'), [])->assertSessionHasNoErrors();
        $trial = TrialRequest::query()->where('user_id', $customer->id)->firstOrFail();
        Membership::factory()->for($customer)->create(['starts_on' => today()->addDay(), 'expires_on' => today()->addMonth()]);

        $this->actingAs($staff)->post(route('staff.trial-requests.approve', $trial), [
            'identity_verified' => 1, 'start_date' => today()->toDateString(),
        ])->assertSessionHasErrors('trial');
        $this->assertDatabaseCount('trial_entitlements', 0);
    }
}
