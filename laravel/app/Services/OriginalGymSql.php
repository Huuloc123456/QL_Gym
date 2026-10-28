<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

class OriginalGymSql
{
    public const ARCHIVED_TABLES = ['CHUYENMON', 'LOPHOC', 'DANGKYLOP', 'DANGKYPT', 'LICHTAPPT', 'LICHLOP', 'NHANVIEN_CHUYENMON'];

    public const TABLES = ['CHUCVU' => 'MACHUCVU', 'LOAIKHACHHANG' => 'MALOAIKH', 'CHUYENMON' => 'MACM',
        'GOITAP' => 'MAGOITAP', 'LOAISANPHAM' => 'MALOAISP', 'KHACHHANG' => 'MAKH', 'NHANVIEN' => 'MANV',
        'SANPHAM' => 'MASP', 'LOPHOC' => 'MALOP', 'CHECKIN' => 'MACHECKIN', 'DIACHI' => 'MADC',
        'HOADON' => 'MAHD', 'LOGINHISTORY' => 'ID', 'HINHANH' => 'MAHINH', 'CHITIETGIOHANG' => 'MACHITIETGH',
        'DANGKYGOITAP' => 'MADKGT', 'DANGKYLOP' => 'MADKLOP', 'DANGKYPT' => 'MADKPT', 'LICHTAPPT' => 'MALICHPT',
        'LICHLOP' => 'MALICHLOP', 'CHITIETHOADON' => 'MACTHD', 'NHANVIEN_CHUYENMON' => 'MANV'];

    public const PROCEDURES = ['SP_KHACHHANGDANGKY', 'sp_KhachHangLogin', 'SP_KHOIPHUCMATKHAU',
        'sp_LayChiTietHoaDon', 'SP_GRANT_ROLE_TO_USER', 'GET_SQL_USERS', 'SP_CREATE_MANAGER_USER',
        'SP_LOCK_USER', 'SP_UNLOCK_USER', 'SP_GET_AUDIT_LOGS', 'SP_DANGKYTAPTHU'];

    public function import(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            if (Schema::hasTable($table)) {
                throw new LogicException('Bảng SQL gốc đã tồn tại: '.$table.'. Không ghi đè dữ liệu.');
            }
        }
        $delimiter = ';';
        $statement = '';
        foreach (file(database_path('sql/ql_gym_mysql.sql')) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            if (str_starts_with($trimmed, 'DELIMITER ')) {
                $delimiter = trim(substr($trimmed, 10));

                continue;
            }
            $statement .= $line;
            if (! str_ends_with($trimmed, $delimiter)) {
                continue;
            }
            $sql = trim(substr(rtrim($statement), 0, -strlen($delimiter)));
            $statement = '';
            if (preg_match('/^(DROP DATABASE|CREATE DATABASE|USE )/i', $sql)) {
                continue;
            }
            DB::unprepared($sql);
        }
        if (trim($statement) !== '') {
            throw new LogicException('SQL chưa kết thúc câu lệnh.');
        }
    }

    public function transaction(Closure $operation): mixed
    {
        return DB::transaction(function () use ($operation): mixed {
            // Supplied procedures allocate MAX + 1; serialize application writers on this row.
            DB::table('gym_write_lock')->where('id', 1)->lockForUpdate()->first();

            return $operation();
        }, 3);
    }

    public function nextId(string $table): int
    {
        if (! isset(self::TABLES[$table]) || DB::transactionLevel() === 0) {
            throw new LogicException('Cấp mã SQL cần bảng được phép và transaction.');
        }

        return (int) DB::table($table)->max(self::TABLES[$table]) + 1;
    }

    public function call(string $procedure, array $arguments = [], ?string $connection = null): array
    {
        if (! in_array($procedure, self::PROCEDURES, true)) {
            throw new LogicException('Procedure không được phép.');
        }
        $statement = DB::connection($connection)->getPdo()->prepare('CALL `'.$procedure.'`('.implode(',', array_fill(0, count($arguments), '?')).')');
        $statement->execute($arguments);
        $sets = [];
        do {
            if ($statement->columnCount() > 0) {
                $sets[] = $statement->fetchAll(\PDO::FETCH_OBJ);
            }
        } while ($statement->nextRowset());
        $statement->closeCursor();

        return $sets;
    }

    public function audit(string $action, string $subject, array $data = []): void
    {
        DB::table('gym_action_logs')->insert(['user_id' => auth()->id(), 'action' => $action,
            'subject' => $subject, 'data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'created_at' => now()]);
    }

    public static function image(?string $path, ?int $product = null): string
    {
        if ($path && str_starts_with($path, 'products/') && preg_match('~^products/[a-zA-Z0-9_.-]+$~', $path)) {
            return asset('storage/'.$path);
        }
        // These five source records reference photos of different products; retain SQL data but hide misleading photos.
        if (in_array($product, [58, 59, 60, 61, 62], true)) {
            return asset('images/product-placeholder.svg');
        }
        $file = basename(str_replace('\\', '/', $path ?? ''));

        return $file && is_file(public_path('images/gym/'.$file))
            ? asset('images/gym/'.rawurlencode($file)) : asset('images/product-placeholder.svg');
    }
}
