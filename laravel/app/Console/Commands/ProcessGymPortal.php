<?php

namespace App\Console\Commands;

use App\Services\GymPortalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProcessGymPortal extends Command
{
    protected $signature = 'gym:process {--mail : Gửi email thông báo đã được bật trong cấu hình web}';

    protected $description = 'Hủy đơn quá hạn chưa thu tiền, tạo nhắc lịch và gửi thông báo';

    public function handle(GymPortalService $portal): int
    {
        $lock = Cache::lock('gym-portal-process', 1800);
        if (! $lock->get()) {
            $this->info('Một tiến trình khác đang xử lý.');

            return self::SUCCESS;
        }
        try {
            $expired = $portal->expireOrders();
            $portal->reminders();
            $sent = $this->option('mail') ? $portal->deliverMessages() : 0;
            DB::table('gym_settings')->where('id', 1)->update(['last_processed_at' => now()]);
            $this->info('Đơn quá hạn: '.$expired.'. Thông báo email đã xử lý: '.$sent.'.');
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
