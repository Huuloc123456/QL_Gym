<?php

namespace App\Models;

use Database\Factories\GymProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GymProduct extends Model
{
    /** @use HasFactory<GymProductFactory> */
    use HasFactory;

    protected $table = 'SANPHAM';

    protected $primaryKey = 'MASP';

    protected $keyType = 'int';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['MASP', 'TENSP', 'MALOAISP', 'DONGIA', 'SOLUONGTON', 'GIAKHUYENMAI', 'HANG', 'XUATXU', 'BAOHANH', 'MOTACHUNG', 'MOTACHITIET'];

    protected function casts(): array
    {
        return ['MASP' => 'integer', 'MALOAISP' => 'integer', 'DONGIA' => 'integer', 'SOLUONGTON' => 'integer', 'GIAKHUYENMAI' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(GymProductCategory::class, 'MALOAISP', 'MALOAISP');
    }
}
