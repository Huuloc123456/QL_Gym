<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RagDocument extends Model
{
    use SoftDeletes;

    protected $fillable = ['document_key', 'title', 'content', 'is_active', 'synced_at', 'revision', 'sync_error'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'synced_at' => 'datetime', 'revision' => 'integer'];
    }
}
