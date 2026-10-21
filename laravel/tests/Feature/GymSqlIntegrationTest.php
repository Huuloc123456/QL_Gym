<?php

namespace Tests\Feature;

use App\Models\MembershipOrder;
use App\Models\TrialRequest;
use App\Models\User;
use App\Services\OriginalGymSql;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class GymSqlIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_original_objects_are_installed_and_all_public_and_management_pages_render(): void
    {
        $database = DB::connection()->getDatabaseName();
        $this->assertCount(11, DB::table('information_schema.ROUTINES')->where('ROUTINE_SCHEMA', $database)->where('ROUTINE_TYPE', 'PROCEDURE')->get());
        $this->assertSame(1, DB::table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', $database)->count());
        $this->assertSame(1, DB::table('information_schema.ROUTINES')->where('ROUTINE_SCHEMA', $database)->where('ROUTINE_TYPE', 'FUNCTION')->count());
        foreach (['/', '/san-pham', '/san-pham/6', '/lop-hoc', '/lop-hoc/1', '/huan-luyen-vien'] as $url) {
            $this->get($url)->assertOk();
        }
        $manager = User::factory()->manager()->create();
        MembershipOrder::factory()->create();
        $this->actingAs($manager);
        foreach (['products' => 'SANPHAM', 'classes' => 'LOPHOC', 'customers' => 'KHACHHANG', 'employees' => 'NHANVIEN', 'categories' => 'LOAISANPHAM', 'positions' => 'CHUCVU', 'tiers' => 'LOAIKHACHHANG', 'specialties' => 'CHUYENMON'] as $resource => $table) {
            $this->get(route('gym.admin.index', $resource))->assertOk();
            $this->get(route('gym.admin.create', $resource))->assertOk();
            $id = DB::table($table)->min(OriginalGymSql::TABLES[$table]);
            $this->get(route('gym.admin.edit', [$resource, $id]))->assertOk();
        }
        foreach (['gym.database.index', 'gym.database.audit', 'gym.invoices.index', 'gym.calendar'] as $route) {
            $this->get(route($route))->assertOk();
        }
        foreach (array_keys(OriginalGymSql::TABLES) as $table) {
            $this->get(route('gym.database.table', $table))->assertOk();
        }
        $this->get(route('gym.invoices.show', 182))->assertOk();
        $this->get(route('gym.database.table', 'users'))->assertNotFound();
    }

    public function test_signup_calls_original_age_classification_and_stores_only_hashes(): void
    {
        $this->travelTo('2027-01-10 08:00');
        $this->post(route('register.store'), ['name' => 'Học viên mới', 'username' => 'hocvien_sql', 'phone' => '0909001234', 'email' => 'hocvien@example.test', 'birth_date' => '2007-01-01', 'gender' => 'female', 'password' => 'StrongPass2027', 'password_confirmation' => 'StrongPass2027'])->assertRedirect()->assertSessionHasNoErrors();
        $user = User::where('username', 'hocvien_sql')->firstOrFail();
        $source = DB::table('KHACHHANG')->where('MAKH', $user->sql_customer_id)->first();
        $this->assertSame('Học sinh, Sinh viên', DB::table('LOAIKHACHHANG')->where('MALOAIKH', $source->MALOAIKH)->value('TENLOAI'));
        $this->assertTrue(Hash::check('StrongPass2027', $source->MATKHAU));
        $this->assertSame('Nữ', $source->GIOITINH);
    }

    public function test_login_does_not_require_email_otp_verification(): void
    {
        $customer = User::factory()->create();

        $this->post(route('login.store'), ['username' => $customer->username, 'password' => 'password', 'otp' => 1])
            ->assertRedirect(route('my.memberships.index'))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($customer);
        $this->assertSame(1, DB::table('LOGINHISTORY')->where('USERID', $customer->sql_customer_id)->where('STATUS', 1)->count());
    }

    public function test_login_has_no_email_otp_verification_route(): void
    {
        $this->assertFalse(Route::has('login.otp'));
        $this->assertFalse(Route::has('login.otp.verify'));
    }

    public function test_disabled_sessions_are_logged_out_and_customers_cannot_access_internal_database(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('gym.database.index'))->assertForbidden();
        $this->post(route('gym.database.account-action'), ['action' => 'lock', 'username' => 'root'])->assertForbidden();
        $customer->forceFill(['is_active' => false])->saveQuietly();
        $this->get(route('gym.cart'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_incomplete_original_invoice_cannot_be_collected_confirmed_or_signed(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->post(route('gym.invoices.receive', 182), ['amount' => 589000, 'method' => 'cash', 'token' => (string) Str::uuid()])->assertSessionHasErrors('invoice');
        $this->post(route('gym.invoices.confirm', 182))->assertSessionHasErrors('invoice');
        $this->post(route('gym.invoices.sign', 138))->assertSessionHasErrors('invoice');
        $this->assertSame(0, DB::table('gym_receipts')->where('invoice_id', 182)->count());
    }

    public function test_database_account_procedures_on_isolated_mysql(): void
    {
        if ((int) config('database.connections.mysql.port') !== 3318) {
            $this->markTestSkipped('Chỉ chạy thao tác tài khoản trên MySQL kiểm thử cô lập cổng 3318.');
        }
        config(['database.connections.gym_admin.username' => 'root', 'database.connections.gym_admin.password' => '']);
        $this->artisan('gym:link-sql', ['--roles' => true])->assertSuccessful();
        $manager = User::factory()->manager()->create();
        $username = 'gym_web_test_'.Str::lower(Str::random(8));
        try {
            $this->actingAs($manager)->post(route('gym.database.account-action'), ['action' => 'create', 'username' => $username, 'password' => 'GymIsolated2027!'])->assertRedirect()->assertSessionHasNoErrors();
            $this->post(route('gym.database.account-action'), ['action' => 'grant', 'username' => $username, 'role' => 'gym_web_reader'])->assertRedirect()->assertSessionHasNoErrors();
            $this->get(route('gym.database.accounts'))->assertOk()->assertSee($username)->assertDontSee('GymIsolated2027!');
            $this->post(route('gym.database.account-action'), ['action' => 'lock', 'username' => $username])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame('Y', DB::connection('gym_admin')->table('mysql.user')->where('User', $username)->value('account_locked'));
            $this->post(route('gym.database.account-action'), ['action' => 'unlock', 'username' => $username])->assertRedirect()->assertSessionHasNoErrors();
            $pdo = new \PDO('mysql:host=127.0.0.1;port=3318;dbname='.DB::connection()->getDatabaseName(), $username, 'GymIsolated2027!');
            $this->assertGreaterThan(0, (int) $pdo->query('SELECT COUNT(*) FROM SANPHAM')->fetchColumn());
            $this->post(route('gym.database.account-action'), ['action' => 'grant', 'username' => 'root', 'role' => 'gym_web_reader'])->assertSessionHasErrors('username');
        } finally {
            DB::connection('gym_admin')->unprepared('DROP USER IF EXISTS '.DB::connection('gym_admin')->getPdo()->quote($username)."@'%'");
        }
    }

    public function test_trial_approval_uses_original_days_once_and_allows_checkin_without_paid_membership(): void
    {
        $staff = User::factory()->employee()->create();
        $trial = TrialRequest::factory()->create();
        $package = (int) DB::table('GOITAP')->where('THOIHAN', 1)->value('MAGOITAP');
        $this->actingAs($staff)->post(route('staff.trial-requests.approve', $trial), ['package' => $package])->assertSessionHasErrors('identity_verified');
        $this->post(route('staff.trial-requests.approve', $trial), ['package' => $package, 'identity_verified' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $customer = User::where('phone', $trial->phone)->firstOrFail();
        $registration = DB::table('DANGKYGOITAP')->where('MADKGT', $trial->fresh()->sql_membership_id)->first();
        $this->assertSame(1, (int) CarbonImmutable::parse($registration->NGAYBATDAU)->diffInDays($registration->NGAYKETTHUC));
        $this->assertFalse(Hash::check('123456', DB::table('KHACHHANG')->where('MAKH', $customer->sql_customer_id)->value('MATKHAU')));
        $this->post(route('staff.trial-requests.approve', $trial), ['package' => $package, 'identity_verified' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('DANGKYGOITAP')->where('MAKH', $customer->sql_customer_id)->count());
        $this->post(route('staff.checkins.store'), ['member' => $trial->phone])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('check_ins', ['user_id' => $customer->id, 'membership_id' => null]);
        $this->assertDatabaseHas('CHECKIN', ['MAKH' => $customer->sql_customer_id, 'TRANGTHAI' => 'Hợp lệ']);
    }
}
