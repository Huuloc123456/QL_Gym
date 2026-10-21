<?php

namespace Tests\Feature;

use App\Services\MembershipPeriod;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MembershipPeriodTest extends TestCase
{
    #[TestWith(['2026-01-31', 1, '2026-03-01', '2026-02-28'])]
    #[TestWith(['2028-01-30', 1, '2028-03-01', '2028-02-29'])]
    #[TestWith(['2028-02-29', 12, '2029-03-01', '2029-02-28'])]
    #[TestWith(['2026-01-31', 2, '2026-03-31', '2026-03-30'])]
    #[TestWith(['2026-08-31', 1, '2026-10-01', '2026-09-30'])]
    #[TestWith(['2026-10-02', 1, '2026-11-02', '2026-11-01'])]
    public function test_calendar_months_preserve_the_last_training_day(
        string $startsOn,
        int $months,
        string $expiry,
        string $lastTrainingDay,
    ): void {
        $period = app(MembershipPeriod::class);

        $expiresOn = $period->expiresOn($startsOn, $months);

        $this->assertSame($expiry.' 00:00:00', $expiresOn->format('Y-m-d H:i:s'));
        $this->assertSame($lastTrainingDay, $period->lastTrainingDay($expiresOn)->toDateString());
    }
}
