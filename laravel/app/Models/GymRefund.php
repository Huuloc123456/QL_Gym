<?php

namespace App\Models;

use Database\Factories\GymRefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GymRefund extends Model
{
    /** @use HasFactory<GymRefundFactory> */
    use HasFactory;

    protected $fillable = ['source_type', 'source_id', 'target_type', 'target_id', 'amount', 'method', 'reference', 'reason', 'recorded_by', 'token', 'refunded_at'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'refunded_at' => 'datetime'];
    }
}
