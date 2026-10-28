<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GymCommerceService;
use Database\Seeders\GymDemoScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GymDemoScenarioSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_is_repeatable_and_creates_consistent_role_scenarios(): void
    {
        Storage::fake('local');
        $this->travelTo('2027-02-01 10:00');
        $originalProducts = DB::table('SANPHAM')->count();
        $originalInvoices = DB::table('HOADON')->count();
        $this->seed(GymDemoScenarioSeeder::class);
        $manifest = json_decode(Storage::disk('local')->get('demo/scenario.json'), true);
        $this->assertCount(12, $manifest['accounts']);
        $this->assertSame($originalProducts + 3, DB::table('SANPHAM')->count());
        $counts = [DB::table('HOADON')->count(), DB::table('gym_receipts')->count(), DB::table('SANPHAM')->sum('SOLUONGTON'), DB::table('CHECKIN')->count()];
        $this->assertGreaterThan($originalInvoices, $counts[0]);
        $this->assertSame(0, app(GymCommerceService::class)->available($manifest['products']['held']));
        $this->assertSame(30, app(GymCommerceService::class)->available($manifest['products']['manual']));
        $this->assertSame(50000, app(GymCommerceService::class)->received($manifest['invoices']['partial_delivery']));
        $this->seed(GymDemoScenarioSeeder::class);
        $this->assertSame($counts, [DB::table('HOADON')->count(), DB::table('gym_receipts')->count(), DB::table('SANPHAM')->sum('SOLUONGTON'), DB::table('CHECKIN')->count()]);
        $this->assertTrue(Hash::check('DemoGym@2026', User::where('username', 'demo_khach')->firstOrFail()->password));
        $this->actingAs(User::where('username', 'demo_hethan')->firstOrFail())->get(route('my.memberships.index'))->assertOk()->assertViewHas('expiredMemberships', fn ($memberships) => $memberships->total() === 1);
        $this->actingAs(User::where('username', 'demo_kho')->firstOrFail())->get(route('staff.products.index'))->assertOk();
        $this->get(route('gym.invoices.index'))->assertForbidden();
        $this->get(route('staff.checkins.index'))->assertForbidden();
        $this->assertFalse(User::where('username', 'demo_khoa')->firstOrFail()->is_active);
    }
}
