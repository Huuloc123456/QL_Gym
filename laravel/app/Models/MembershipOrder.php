<?php

namespace App\Models;

use Database\Factories\MembershipOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MembershipOrder extends Model
{
    /** @use HasFactory<MembershipOrderFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = ['user_id', 'gym_package_id', 'amount', 'start_date', 'payment_method', 'status', 'paid_at', 'confirmed_by_user_id', 'cancellation_requested_at'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'start_date' => 'date', 'paid_at' => 'datetime', 'cancellation_requested_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gymPackage(): BelongsTo
    {
        return $this->belongsTo(GymPackage::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function receivedAmount(): int
    {
        return (int) $this->payments()->sum('amount');
    }
}
