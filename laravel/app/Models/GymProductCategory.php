<?php

namespace App\Models;

use Database\Factories\GymProductCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GymProductCategory extends Model
{
    /** @use HasFactory<GymProductCategoryFactory> */
    use HasFactory;

    protected $table = 'LOAISANPHAM';

    protected $primaryKey = 'MALOAISP';

    protected $keyType = 'int';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['MALOAISP', 'TENLOAISP'];

    protected function casts(): array
    {
        return ['MALOAISP' => 'integer'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(GymProduct::class, 'MALOAISP', 'MALOAISP');
    }
}
