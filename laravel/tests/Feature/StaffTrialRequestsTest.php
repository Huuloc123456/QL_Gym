<?php

namespace Tests\Feature;

use App\Models\TrialRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class StaffTrialRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_and_manager_can_review_trial_requests(): void
    {
        $trialRequest = TrialRequest::factory()->create([
            'name' => 'Nguyen Van An',
            'phone' => '0901234567',
            'status' => 'received',
        ]);

        foreach ([User::factory()->employee()->create(), User::factory()->manager()->create()] as $staff) {
            $this->actingAs($staff)->get(route('staff.trial-requests.index'))
                ->assertOk()
                ->assertSee('Nguyen Van An')
                ->assertSee('0901234567');
        }

        $this->assertTrue(Gate::forUser(User::factory()->employee()->create())->allows('viewAny', TrialRequest::class));
        $this->assertFalse(Gate::forUser(User::factory()->create(['role' => 'customer']))->allows('viewAny', TrialRequest::class));
    }

    public function test_staff_marks_a_request_contacted_and_the_record_keeps_who_handled_it(): void
    {
        $employee = User::factory()->employee()->create();
        $trialRequest = TrialRequest::factory()->create(['status' => 'received']);

        $this->actingAs($employee)->post(route('staff.trial-requests.contact', $trialRequest))
            ->assertRedirect(route('staff.trial-requests.index'))
            ->assertSessionHas('success');

        $trialRequest->refresh();
        $this->assertSame('contacted', $trialRequest->status);
        $this->assertSame($employee->id, $trialRequest->handled_by);
        $this->assertNotNull($trialRequest->handled_at);

        $this->actingAs($employee)->post(route('staff.trial-requests.contact', $trialRequest))
            ->assertForbidden();
    }

    public function test_staff_can_filter_trial_requests_by_status_and_contact_details(): void
    {
        $manager = User::factory()->manager()->create();
        TrialRequest::factory()->create([
            'name' => 'An Chưa liên hệ',
            'phone' => '0901111111',
            'status' => 'received',
        ]);
        TrialRequest::factory()->create([
            'name' => 'An Đã liên hệ',
            'phone' => '0902222222',
            'status' => 'contacted',
        ]);

        $this->actingAs($manager)->get(route('staff.trial-requests.index', [
            'status' => 'received',
            'search' => '0901111111',
        ]))
            ->assertOk()
            ->assertSee('An Chưa liên hệ')
            ->assertDontSee('An Đã liên hệ');
    }

    public function test_customers_cannot_open_or_process_the_staff_trial_inbox(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $trialRequest = TrialRequest::factory()->create();

        $this->actingAs($customer)->get(route('staff.trial-requests.index'))->assertForbidden();
        $this->actingAs($customer)->post(route('staff.trial-requests.contact', $trialRequest))->assertForbidden();
        $this->post(route('logout'));
        $this->get(route('staff.trial-requests.index'))->assertRedirect(route('login'));
    }
}
