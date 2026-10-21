<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class MembershipPeriod
{
    public function expiresOn(string|CarbonImmutable $startsOn, int $durationMonths): CarbonImmutable
    {
        $startDate = CarbonImmutable::parse($startsOn, config('app.timezone'))->startOfDay();
        $expiresOn = $startDate->addMonthsNoOverflow($durationMonths);

        return $expiresOn->day < $startDate->day ? $expiresOn->addDay() : $expiresOn;
    }

    public function lastTrainingDay(string|CarbonImmutable $expiresOn): CarbonImmutable
    {
        return CarbonImmutable::parse($expiresOn, config('app.timezone'))->startOfDay()->subDay();
    }

    public function overlaps(
        string|CarbonImmutable $startsOn,
        string|CarbonImmutable $expiresOn,
        string|CarbonImmutable $otherStartsOn,
        string|CarbonImmutable $otherExpiresOn,
    ): bool {
        $starts = CarbonImmutable::parse($startsOn, config('app.timezone'));
        $expires = CarbonImmutable::parse($expiresOn, config('app.timezone'));
        $otherStarts = CarbonImmutable::parse($otherStartsOn, config('app.timezone'));
        $otherExpires = CarbonImmutable::parse($otherExpiresOn, config('app.timezone'));

        return $starts->lt($otherExpires) && $expires->gt($otherStarts);
    }
}
