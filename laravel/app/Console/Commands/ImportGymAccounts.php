<?php

namespace App\Console\Commands;

use App\Models\CheckIn;
use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\User;
use App\Services\GymIdentityService;
use App\Services\OriginalGymSql;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportGymAccounts extends Command
{
    protected $signature = 'gym:link-sql {--roles : Tạo hai role MySQL giới hạn trong database ứng dụng}';

    protected $description = 'Liên kết dữ liệu Laravel hiện có với SQL gốc, không ghi đè tài khoản trùng';

    public function handle(GymIdentityService $identities, OriginalGymSql $sql): int
    {
        $sql->transaction(function () use ($identities): void {
            foreach (DB::table('GOITAP')->orderBy('MAGOITAP')->get() as $row) {
                $package = GymPackage::query()->where('sql_package_id', $row->MAGOITAP)->first()
                    ?? GymPackage::query()->whereNull('sql_package_id')->where('duration_months', $row->THOIHAN)->orderBy('id')->first()
                    ?? new GymPackage;
                $package->forceFill(['name' => $row->TENGOI, 'description' => $row->MOTA, 'duration_months' => $row->THOIHAN, 'price' => $row->GIA, 'is_active' => $package->exists ? $package->is_active : true, 'sql_package_id' => $row->MAGOITAP])->saveQuietly();
            }
            foreach (GymPackage::query()->whereNull('sql_package_id')->get() as $package) {
                $identities->package($package);
            }
            foreach (User::query()->get() as $user) {
                $identities->sync($user);
            }
            foreach (['KHACHHANG' => 'customer', 'NHANVIEN' => 'employee'] as $table => $kind) {
                foreach (DB::table($table)->get() as $row) {
                    if (! $identities->importAccount($row, $kind)) {
                        $this->warn('Cần đối chiếu tên đăng nhập trùng: '.$row->TENDANGNHAP);
                    }
                }
            }
            foreach (Membership::query()->whereNull('sql_membership_id')->with('order.user', 'order.gymPackage')->get() as $membership) {
                $identities->recordMembership($membership, $membership->order);
            }
            foreach (CheckIn::query()->get() as $visit) {
                $identities->syncCheckIn($visit);
            }
            foreach (DB::table('DIACHI')->select('MAKH')->distinct()->pluck('MAKH') as $customer) {
                if (! DB::table('DIACHI')->where('MAKH', $customer)->where('LADIACHIMACDINH', 1)->exists()) {
                    $first = DB::table('DIACHI')->where('MAKH', $customer)->orderBy('MADC')->value('MADC');
                    DB::table('DIACHI')->where('MADC', $first)->update(['LADIACHIMACDINH' => 1]);
                }
            }
        });
        if ($this->option('roles')) {
            $connection = DB::connection('gym_admin');
            $database = str_replace('`', '``', DB::connection()->getDatabaseName());
            foreach (['gym_web_reader' => 'SELECT', 'gym_web_operator' => 'SELECT, INSERT, UPDATE, DELETE'] as $role => $permissions) {
                $connection->unprepared("CREATE ROLE IF NOT EXISTS '".$role."'");
                $connection->unprepared('GRANT '.$permissions.' ON `'.$database."`.* TO '".$role."'");
            }
        }
        $this->info('Đã liên kết tài khoản, gói tập, kỳ hội viên và check-in với SQL gốc.');

        return self::SUCCESS;
    }
}
