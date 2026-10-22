<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\GymMessageNotification;
use App\Services\GymCommerceService;
use App\Services\GymPortalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class GymPortalCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function order(User $customer, array $data = []): int
    {
        DB::table('SANPHAM')->insertOrIgnore(['MASP' => 9001, 'TENSP' => 'Sản phẩm kiểm thử', 'MALOAISP' => 3, 'DONGIA' => 100000, 'SOLUONGTON' => 10]);
        $shop = app(GymCommerceService::class);
        $shop->add($customer, 'product', 9001, 1);

        return $shop->checkout($customer, $data + ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash']);
    }

    private function settings(): array
    {
        return ['name' => 'Phòng gym kiểm thử', 'address' => '10 Đường A', 'phone' => '0900001234', 'hours' => '06:00–22:00', 'bank_name' => 'Ngân hàng kiểm thử', 'bank_account' => '123456789', 'bank_holder' => 'CHU TAI KHOAN', 'payment_hours' => 24, 'reminder_days' => 3, 'shipping_fee' => 30000, 'email_enabled' => 0];
    }

    public function test_invoice_does_not_render_a_qr_when_bank_vietqr_is_not_configured(): void
    {
        $customer = User::factory()->create();
        $invoice = $this->order($customer, ['payment_method' => 'bank_transfer']);
        DB::table('gym_settings')->where('id', 1)->update([
            'bank_code' => null,
            'bank_account' => null,
            'bank_account_name' => null,
        ]);

        $this->actingAs($customer)
            ->get(route('gym.invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee('Mã QR DEMO')
            ->assertDontSee('data-demo-qr', false)
            ->assertDontSee('img.vietqr.io/image/', false);
    }

    public function test_bank_transfer_invoice_uses_real_vietqr_when_bank_details_are_configured(): void
    {
        $customer = User::factory()->create();
        $invoice = $this->order($customer, ['payment_method' => 'bank_transfer']);
        DB::table('gym_settings')->where('id', 1)->update([
            'bank_code' => 'vietcombank',
            'bank_account' => '0123456789',
            'bank_account_name' => 'THE GYM',
        ]);

        $this->actingAs($customer)
            ->get(route('gym.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('https://img.vietqr.io/image/vietcombank-0123456789-compact2.png', false)
            ->assertSee('amount=100000', false)
            ->assertSee('addInfo=GYM%20HD'.$invoice, false)
            ->assertDontSee('Mã QR DEMO');
    }

    public function test_unpaid_order_expires_once_and_releases_reservation_without_changing_physical_stock(): void
    {
        $this->travelTo('2027-01-10 08:00');
        DB::table('gym_settings')->where('id', 1)->update(['payment_hours' => 2]);
        $customer = User::factory()->create();
        $invoice = $this->order($customer);
        $this->assertSame(9, app(GymCommerceService::class)->available(9001));
        $this->travelTo('2027-01-10 09:59');
        $this->assertSame(0, app(GymPortalService::class)->expireOrders());
        $this->travelTo('2027-01-10 10:00');
        $this->artisan('gym:process')->assertSuccessful();
        $this->artisan('gym:process')->assertSuccessful();
        $this->assertDatabaseHas('HOADON', ['MAHD' => $invoice, 'TRANGTHAI' => 'Hủy']);
        $this->assertDatabaseHas('gym_fulfillments', ['invoice_id' => $invoice, 'state' => 'expired']);
        $this->assertSame(10, app(GymCommerceService::class)->available(9001));
        $this->assertDatabaseHas('SANPHAM', ['MASP' => 9001, 'SOLUONGTON' => 10]);
        $this->assertSame(1, DB::table('gym_messages')->where('event_key', $customer->id.':invoice:'.$invoice.':expired')->count());
    }

    public function test_partial_receipt_protects_order_from_expiry_and_allows_staff_to_finish_collection(): void
    {
        $this->travelTo('2027-01-10 08:00');
        DB::table('gym_settings')->where('id', 1)->update(['payment_hours' => 1]);
        $customer = User::factory()->create();
        $staff = User::factory()->employee()->create();
        $invoice = $this->order($customer);
        $this->actingAs($staff)->post(route('gym.invoices.receive', $invoice), ['amount' => 40000, 'method' => 'cash', 'token' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();
        $this->travelTo('2027-01-10 11:00');
        $this->assertSame(0, app(GymPortalService::class)->expireOrders());
        $this->post(route('gym.invoices.receive', $invoice), ['amount' => 60000, 'method' => 'cash', 'token' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('gym.invoices.confirm', $invoice))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('HOADON', ['MAHD' => $invoice, 'TRANGTHAI' => 'Đã thanh toán']);
        $this->assertDatabaseHas('SANPHAM', ['MASP' => 9001, 'SOLUONGTON' => 9]);
        $this->assertSame('2027-01-10 11:00:00', DB::table('gym_fulfillments')->where('invoice_id', $invoice)->value('paid_at'));
    }

    public function test_late_first_receipt_is_blocked_even_before_scheduler_runs(): void
    {
        $this->travelTo('2027-01-10 08:00');
        $invoice = $this->order(User::factory()->create());
        $staff = User::factory()->employee()->create();
        $this->travelTo('2027-01-11 08:01');
        $this->actingAs($staff)->post(route('gym.invoices.receive', $invoice), ['amount' => 100000, 'method' => 'cash', 'token' => (string) Str::uuid()])->assertSessionHasErrors('invoice');
        $this->assertSame(0, DB::table('gym_receipts')->where('invoice_id', $invoice)->count());
    }

    public function test_delivery_fee_is_server_calculated_after_discount_and_frozen_when_settings_change(): void
    {
        DB::table('gym_settings')->where('id', 1)->update(['shipping_fee' => 30000]);
        $customer = User::factory()->create();
        DB::table('KHACHHANG')->where('MAKH', $customer->sql_customer_id)->update(['MALOAIKH' => 3]);
        $address = DB::table('DIACHI')->insertGetId(['MAKH' => $customer->sql_customer_id, 'TINHTHANHPHO' => 'TP A', 'QUANHUYEN' => 'Quận A', 'PHUONGXA' => 'Phường A', 'DIACHICUTHE' => '10 Đường A', 'LADIACHIMACDINH' => 1, 'NGAYTHEM' => now()]);
        $invoice = $this->order($customer, ['mode' => 'delivery', 'address_id' => $address, 'recipient' => 'Người nhận', 'phone' => '0900001234', 'shipping_fee' => 1]);
        $this->assertDatabaseHas('HOADON', ['MAHD' => $invoice, 'TONGTIEN' => 100000, 'GIAMGIA' => 10000, 'THANHTIEN' => 120000]);
        DB::table('gym_settings')->where('id', 1)->update(['shipping_fee' => 90000]);
        $this->actingAs($customer)->get(route('gym.invoices.show', $invoice))->assertOk()->assertSee('30.000');
        $this->assertDatabaseHas('gym_fulfillments', ['invoice_id' => $invoice, 'shipping_fee' => 30000]);
    }

    public function test_messages_are_scoped_and_read_all_does_not_change_another_customer(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $portal = app(GymPortalService::class);
        $portal->message($customer, 'test', 'Thông báo của tôi', 'Nội dung', '/gio-hang');
        $portal->message($other, 'test', 'Thông báo riêng của người khác', 'Nội dung', '/gio-hang');
        $otherMessage = DB::table('gym_messages')->where('user_id', $other->id)->value('id');
        $this->actingAs($customer)->get(route('gym.messages'))->assertOk()->assertSee('Thông báo của tôi')->assertDontSee('Thông báo riêng của người khác');
        $this->post(route('gym.messages.read', $otherMessage))->assertNotFound();
        $this->post(route('gym.messages.read-all'))->assertRedirect();
        $this->assertDatabaseHas('gym_messages', ['id' => $otherMessage, 'read_at' => null]);
        $mine = DB::table('gym_messages')->where('user_id', $customer->id)->first();
        $this->assertNotNull($mine->read_at);
        $this->post(route('gym.messages.read', $mine->id))->assertRedirect('/gio-hang');
    }

    public function test_disabled_email_never_sends_and_enabled_mail_notification_is_delivered_once(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $portal = app(GymPortalService::class);
        $portal->message($customer, 'disabled', 'Thông báo', 'Nội dung', '/thong-bao');
        $this->assertSame(0, $portal->deliverMessages());
        Notification::assertNothingSent();
        DB::table('gym_settings')->where('id', 1)->update(['email_enabled' => true]);
        $portal->message($customer, 'enabled', 'Thông báo mới', 'Nội dung', '/thong-bao');
        $portal->message($customer, 'enabled', 'Thông báo mới', 'Nội dung', '/thong-bao');
        $this->assertSame(1, $portal->deliverMessages());
        $this->assertSame(0, $portal->deliverMessages());
        Notification::assertSentToTimes($customer, GymMessageNotification::class, 1);
        $mail = (new GymMessageNotification('Tiêu đề', 'Nội dung', '/thong-bao'))->toMail($customer);
        $this->assertSame(rtrim(config('app.url'), '/').'/thong-bao', $mail->actionUrl);
    }

    public function test_membership_reminders_are_deduplicated_between_repeated_scheduled_runs(): void
    {
        $this->travelTo('2027-01-10 08:00');
        $customer = User::factory()->create();
        DB::table('DANGKYGOITAP')->insert(['MADKGT' => 9001, 'MAKH' => $customer->sql_customer_id, 'MAGOITAP' => 1, 'NGAYDANGKY' => now(), 'NGAYBATDAU' => now()->subMonth(), 'NGAYKETTHUC' => '2027-01-13 23:59:59', 'TRANGTHAI' => 'Còn hiệu lực']);
        app(GymPortalService::class)->reminders();
        app(GymPortalService::class)->reminders();
        $this->assertSame(1, DB::table('gym_messages')->where('user_id', $customer->id)->count());
        $this->actingAs($customer)->get(route('gym.messages'))->assertSee('13/01/2027');
    }

    public function test_failed_email_is_bounded_and_does_not_expose_transport_secrets(): void
    {
        DB::table('gym_settings')->where('id', 1)->update(['email_enabled' => true]);
        $customer = User::factory()->create();
        $portal = app(GymPortalService::class);
        $portal->message($customer, 'mail-failure', 'Thông báo', 'Nội dung', '/thong-bao');
        Notification::shouldReceive('send')->times(3)->andThrow(new \RuntimeException('smtp-secret-value'));
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->assertSame(0, $portal->deliverMessages());
        }
        $message = DB::table('gym_messages')->where('user_id', $customer->id)->first();
        $this->assertSame(3, $message->attempts);
        $this->assertSame('failed', $message->email_state);
        $this->assertStringNotContainsString('smtp-secret-value', $message->last_error);
    }

    public function test_new_class_session_notifies_each_student_and_teacher_once(): void
    {
        $this->travelTo('2027-01-10 08:00');
        $manager = User::factory()->manager()->create();
        $teacher = User::factory()->employee()->create();
        $students = User::factory()->count(2)->create();
        DB::table('LOPHOC')->insert(['MALOP' => 9001, 'TENLOP' => 'Lớp kiểm thử thông báo', 'MACM' => 1, 'HOCPHI' => 100000, 'NGAYBATDAU' => '2027-01-11', 'NGAYKETTHUC' => '2027-02-11', 'SOBUOI' => 2, 'SISOTOIDA' => 5]);
        DB::table('NHANVIEN_CHUYENMON')->insert(['MANV' => $teacher->sql_employee_id, 'MACM' => 1]);
        foreach ($students as $index => $student) {
            DB::table('DANGKYLOP')->insert(['MADKLOP' => 9001 + $index, 'MAKH' => $student->sql_customer_id, 'MALOP' => 9001, 'NGAYDANGKY' => now(), 'TRANGTHAI' => 'Còn hiệu lực']);
        }
        $this->actingAs($manager)->post(route('gym.class.session', 9001), ['date' => '2027-01-11', 'start' => '09:00', 'end' => '10:00', 'employee' => $teacher->sql_employee_id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(3, DB::table('gym_messages')->count());
        $this->assertSame(1, DB::table('gym_messages')->where('user_id', $teacher->id)->count());
    }

    public function test_password_change_requires_current_password_syncs_sql_and_closes_other_sessions(): void
    {
        $customer = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'other-session', 'user_id' => $customer->id, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($customer)->put(route('gym.password'), ['current_password' => 'wrong', 'password' => 'NewStrong2027!', 'password_confirmation' => 'NewStrong2027!'])->assertSessionHasErrors('current_password');
        $this->put(route('gym.password'), ['current_password' => 'password', 'password' => 'NewStrong2027!', 'password_confirmation' => 'NewStrong2027!'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NewStrong2027!', $customer->fresh()->password));
        $this->assertTrue(Hash::check('NewStrong2027!', DB::table('KHACHHANG')->where('MAKH', $customer->sql_customer_id)->value('MATKHAU')));
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
    }

    public function test_settings_public_information_and_reports_are_complete_and_protected(): void
    {
        $this->travelTo('2027-01-10 08:00');
        $customer = User::factory()->create();
        foreach (['gym.settings', 'manager.reports.index', 'gym.reports.export'] as $route) {
            $this->actingAs($customer)->get(route($route))->assertForbidden();
        }
        $this->put(route('gym.settings.save'), $this->settings())->assertForbidden();
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->put(route('gym.settings.save'), $this->settings())->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('manager.settings.edit'))->assertOk()->assertSee('payment_hours')->assertSee('faq_items[0][question]');
        $this->get(route('gym.settings'))->assertRedirect(route('manager.settings.edit'));
        foreach (['manager.reports.index', 'gym.contact', 'gym.help', 'gym.policy', 'gym.security', 'gym.messages'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('gym.contact'))->assertSee('10 Đường A')->assertSee('06:00–22:00');
        $this->get(route('manager.reports.index', ['from' => '2027-01-11', 'to' => '2027-01-10']))->assertSessionHasErrors('to');
        $this->get(route('manager.reports.index', ['from' => '2020-01-01', 'to' => '2027-01-10']))->assertSessionHasErrors('to');
    }

    public function test_portal_settings_update_preserves_the_existing_settings_column_names(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->put(route('gym.settings.save'), $this->settings())
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('gym_settings', [
            'id' => 1,
            'gym_name' => 'Phòng gym kiểm thử',
            'opening_hours' => '06:00–22:00',
            'bank_account_name' => 'CHU TAI KHOAN',
            'payment_hours' => 24,
            'reminder_days' => 3,
            'shipping_fee' => 30000,
        ]);

        $this->actingAs($manager)->get(route('gym.contact'))
            ->assertOk()->assertSee('Phòng gym kiểm thử')->assertSee('06:00–22:00');
    }

    public function test_report_revenue_cash_and_csv_preserve_scope_and_prevent_formula_injection(): void
    {
        $this->travelTo('2027-01-10 08:00');
        $customer = User::factory()->create(['name' => '=HYPERLINK("bad")']);
        $invoice = $this->order($customer);
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->post(route('gym.invoices.receive', $invoice), ['amount' => 100000, 'method' => 'cash', 'token' => (string) Str::uuid()])->assertRedirect();
        $this->post(route('gym.invoices.confirm', $invoice))->assertRedirect()->assertSessionHasNoErrors();
        $range = ['from' => '2027-01-10', 'to' => '2027-01-10'];
        $this->get(route('manager.reports.index', $range))->assertOk()->assertViewHas('revenue', 100000)->assertViewHas('cash', 100000)->assertViewHas('count', 1);
        $response = $this->get(route('gym.reports.export', $range))->assertDownload('bao-cao-20270110-20270110.csv');
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
        $this->get(route('manager.reports.index', ['from' => '2027-01-11', 'to' => '2027-01-11']))->assertViewHas('revenue', 0);
    }

    public function test_calendar_hides_legacy_pt_sessions_for_every_role(): void
    {
        $this->travelTo('2027-01-10 08:00');
        $customer = User::factory()->create();
        $other = User::factory()->create(['name' => 'Khách riêng bí mật']);
        $trainer = User::factory()->employee()->create();
        DB::table('DANGKYPT')->insert(['MADKPT' => 9001, 'MAKH' => $other->sql_customer_id, 'MANV' => $trainer->sql_employee_id, 'SOBUOI' => 1, 'GIAMOIBUOI' => 100000, 'NGAYDANGKY' => now(), 'TRANGTHAI' => 'Còn hiệu lực']);
        DB::table('LICHTAPPT')->insert(['MALICHPT' => 9001, 'MADKPT' => 9001, 'NGAYTAP' => '2027-01-11', 'GIOBATDAU' => '2027-01-11 09:00:00', 'GIOKETTHUC' => '2027-01-11 10:00:00', 'TRANGTHAI' => 'Chưa tập']);
        $this->actingAs($customer)->get(route('gym.calendar', ['date' => '2027-01-11', 'view' => 'week']))->assertOk()->assertDontSee('Khách riêng bí mật')->assertViewHas('calendar', fn ($calendar) => count($calendar['days']) === 7 && $calendar['events']->isEmpty());
        $this->actingAs($other)->get(route('gym.calendar', ['date' => '2027-01-11', 'view' => 'month']))->assertOk()->assertDontSee('PT lưu lịch sử')->assertViewHas('calendar', fn ($calendar) => count($calendar['days']) === 35 && $calendar['events']->isEmpty());
        $this->get(route('gym.calendar', ['date' => 'invalid']))->assertSessionHasErrors('date');
    }

    public function test_unreconciled_invoice_totals_block_collection_confirmation_and_signing(): void
    {
        $this->travelTo('2027-01-10 08:00');
        $invoice = $this->order(User::factory()->create());
        $manager = User::factory()->manager()->create();
        DB::table('HOADON')->where('MAHD', $invoice)->update(['THANHTIEN' => 100001]);
        $this->actingAs($manager)->post(route('gym.invoices.receive', $invoice), ['amount' => 100000, 'method' => 'cash', 'token' => (string) Str::uuid()])->assertSessionHasErrors('invoice');
        $this->get(route('gym.invoices.show', $invoice))->assertOk()->assertSee('Hóa đơn đang cần đối chiếu')->assertViewHas('needsReconciliation', true);
        DB::table('HOADON')->where('MAHD', $invoice)->update(['TONGTIEN' => 101000, 'THANHTIEN' => 101000]);
        $this->post(route('gym.invoices.confirm', $invoice))->assertSessionHasErrors('invoice');
        DB::table('HOADON')->where('MAHD', $invoice)->update(['TRANGTHAI' => 'Đã thanh toán']);
        $this->post(route('gym.invoices.sign', $invoice))->assertSessionHasErrors('invoice');
        $this->get(route('manager.reports.index', ['from' => '2027-01-10', 'to' => '2027-01-10']))->assertOk()->assertViewHas('incomplete', 1);
        $this->assertSame(0, DB::table('gym_receipts')->where('invoice_id', $invoice)->count());
    }
}
