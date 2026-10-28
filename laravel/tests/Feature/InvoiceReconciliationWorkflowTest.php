<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GymCommerceService;
use App\Services\GymInvoiceReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceReconciliationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function anomalousInvoice(User $customer): int
    {
        DB::table('SANPHAM')->insertOrIgnore([
            'MASP' => 9901,
            'TENSP' => 'Sản phẩm đối soát',
            'MALOAISP' => 3,
            'DONGIA' => 100000,
            'SOLUONGTON' => 10,
        ]);
        $commerce = app(GymCommerceService::class);
        $commerce->add($customer, 'product', 9901, 1);
        $invoice = $commerce->checkout($customer, [
            'token' => (string) Str::uuid(),
            'mode' => 'pickup',
            'payment_method' => 'cash',
        ]);
        DB::table('HOADON')->where('MAHD', $invoice)->update(['THANHTIEN' => 99000]);

        return $invoice;
    }

    public function test_manager_can_assign_and_record_a_blocked_reconciliation_with_audit_history(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->anomalousInvoice(User::factory()->create());

        $this->actingAs($manager)->get(route('manager.settings.edit'))
            ->assertOk()->assertSee('Dữ liệu cần đối chiếu')->assertSee('#'.$invoice);

        $this->actingAs($manager)->put(route('manager.reconciliations.update', $invoice), [
            'category' => 'payable_mismatch',
            'status' => 'blocked',
            'owner_user_id' => $manager->id,
            'evidence' => 'Tổng thanh toán khác tổng dòng hóa đơn.',
            'note' => 'Chờ tìm chứng từ lịch sử trước khi chỉnh.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('gym_invoice_reconciliations', [
            'invoice_id' => $invoice,
            'category' => 'payable_mismatch',
            'status' => 'blocked',
            'owner_user_id' => $manager->id,
            'reviewed_by_user_id' => $manager->id,
        ]);
        $this->assertDatabaseHas('gym_action_logs', [
            'user_id' => $manager->id,
            'action' => 'Đối soát hóa đơn lịch sử',
            'subject' => 'HOADON:'.$invoice,
        ]);
        $this->actingAs($manager)->get(route('manager.settings.edit'))
            ->assertOk()->assertSee('Chờ tìm chứng từ lịch sử trước khi chỉnh.');
    }

    public function test_manager_cannot_mark_an_invoice_resolved_while_its_data_is_still_anomalous(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->anomalousInvoice(User::factory()->create());

        $this->actingAs($manager)->put(route('manager.reconciliations.update', $invoice), [
            'category' => 'payable_mismatch',
            'status' => 'resolved',
            'owner_user_id' => $manager->id,
            'evidence' => 'Đã xem dòng tiền và hóa đơn.',
            'note' => 'Đã xử lý xong.',
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseMissing('gym_invoice_reconciliations', ['invoice_id' => $invoice]);
    }

    public function test_only_a_manager_can_update_reconciliation_and_resolved_requires_corrected_source_data(): void
    {
        $employee = User::factory()->employee()->create();
        $manager = User::factory()->manager()->create();
        $invoice = $this->anomalousInvoice(User::factory()->create());
        $input = [
            'category' => 'payable_mismatch',
            'status' => 'blocked',
            'owner_user_id' => $manager->id,
            'evidence' => 'Tổng thanh toán khác tổng dòng hóa đơn.',
            'note' => 'Chờ tìm chứng từ lịch sử trước khi chỉnh.',
        ];

        $this->actingAs($employee)->get(route('manager.settings.edit'))->assertForbidden();
        $this->put(route('manager.reconciliations.update', $invoice), $input)->assertForbidden();

        DB::table('HOADON')->where('MAHD', $invoice)->update(['THANHTIEN' => 100000]);
        $input['status'] = 'resolved';
        $input['evidence'] = 'Đã đối chiếu chứng từ thanh toán QA-9901.';
        $input['note'] = 'Tổng đã được sửa theo chứng từ đã kiểm tra.';
        $this->actingAs($manager)->put(route('manager.reconciliations.update', $invoice), $input)
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('gym_invoice_reconciliations', [
            'invoice_id' => $invoice,
            'status' => 'resolved',
        ]);
    }

    public function test_reconciliation_compares_legacy_and_current_detail_sources_independently(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->anomalousInvoice(User::factory()->create());
        DB::table('CHITIETHOADON')->insert([
            'MACTHD' => 9901,
            'MAHD' => $invoice,
            'MASP' => 9901,
            'SOLUONG' => 1,
            'DONGIA' => 99000,
        ]);
        DB::table('HOADON')->where('MAHD', $invoice)->update(['THANHTIEN' => 100000]);

        $cases = app(GymInvoiceReconciliationService::class)->cases();
        $case = $cases->firstWhere('invoice_id', $invoice);

        $this->assertNotNull($case);
        $this->assertSame(99000, (int) $case->legacy_line_total);
        $this->assertSame(100000, (int) $case->current_line_total);
        $this->assertSame('detail_sources_disagree', $case->suggested_category);

        $this->actingAs($manager)->get(route('manager.settings.edit'))
            ->assertOk()->assertSee('Hai nguồn chi tiết không khớp');
    }

    public function test_reconciliation_must_be_assigned_to_an_active_manager(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $invoice = $this->anomalousInvoice(User::factory()->create());

        $this->actingAs($manager)->put(route('manager.reconciliations.update', $invoice), [
            'category' => 'payable_mismatch',
            'status' => 'blocked',
            'owner_user_id' => $employee->id,
            'evidence' => 'Tổng thanh toán khác tổng dòng hóa đơn.',
            'note' => 'Chờ tìm chứng từ lịch sử trước khi chỉnh.',
        ])->assertSessionHasErrors('owner_user_id');

        $this->assertDatabaseMissing('gym_invoice_reconciliations', ['invoice_id' => $invoice]);
    }
}
