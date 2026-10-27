<?php

namespace App\Models;

use Database\Factories\TrialEntitlementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrialEntitlement extends Model
{
    /** @use HasFactory<TrialEntitlementFactory> */
    use HasFactory;

    protected $fillable = ['trial_request_id', 'user_id', 'verified_phone', 'starts_at', 'ends_at', 'approved_by', 'revoked_at', 'revocation_reason', 'revoked_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function trialRequest(): BelongsTo
    {
        return $this->belongsTo(TrialRequest::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
