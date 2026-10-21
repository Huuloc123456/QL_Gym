<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeroBanner extends Model
{
    protected $fillable = ['title', 'alt_text', 'image_path', 'position', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
