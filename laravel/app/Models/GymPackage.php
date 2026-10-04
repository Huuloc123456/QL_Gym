<?php

namespace App\Models;

use Database\Factories\GymPackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GymPackage extends Model
{
    /** @use HasFactory<GymPackageFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description', 'price', 'duration_months', 'is_active'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'duration_months' => 'integer', 'is_active' => 'boolean'];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MembershipOrder::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
