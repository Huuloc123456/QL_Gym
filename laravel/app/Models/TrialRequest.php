<?php

namespace App\Models;

use Database\Factories\TrialRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrialRequest extends Model
{
    /** @use HasFactory<TrialRequestFactory> */
    use HasFactory;

    protected $fillable = ['name', 'phone', 'email', 'status', 'handled_by', 'handled_at', 'user_id', 'desired_date', 'decision_reason', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime', 'desired_date' => 'date', 'decided_at' => 'datetime'];
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function entitlement(): HasOne
    {
        return $this->hasOne(TrialEntitlement::class);
    }
}
