<?php

namespace App\Repositories;

use App\Models\GymPackage;
use Illuminate\Database\Eloquent\Collection;

class GymPackageRepository
{
    /**
     * @return Collection<int, GymPackage>
     */
    public function activePackages(): Collection
    {
        return GymPackage::query()
            ->where('is_active', true)
            ->orderBy('duration_months')
            ->get();
    }

    public function findActiveOrFail(int $packageId): GymPackage
    {
        return GymPackage::query()
            ->where('is_active', true)
            ->findOrFail($packageId);
    }

    /** @return Collection<int, GymPackage> */
    public function forManagement(): Collection
    {
        return GymPackage::query()
            ->orderBy('duration_months')
            ->orderBy('name')
            ->get();
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): GymPackage
    {
        return GymPackage::query()->create($attributes + ['is_active' => true]);
    }

    /** @param array<string, mixed> $attributes */
    public function update(GymPackage $package, array $attributes): void
    {
        $package->update($attributes);
    }

    public function toggleActive(GymPackage $package): void
    {
        $package->update(['is_active' => ! $package->is_active]);
    }
}
