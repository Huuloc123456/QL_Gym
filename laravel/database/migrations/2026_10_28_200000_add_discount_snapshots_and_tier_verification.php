<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gym_invoice_lines', function (Blueprint $table): void {
            $table->unsignedBigInteger('original_unit_price')->nullable()->after('unit_price');
        });

        Schema::table('gym_fulfillments', function (Blueprint $table): void {
            $table->unsignedTinyInteger('pricing_snapshot_version')->nullable()->after('checkout_token');
        });

        Schema::table('LOAIKHACHHANG', function (Blueprint $table): void {
            $table->boolean('REQUIRES_VERIFICATION')->default(false)->after('MUCGIAM');
        });
        DB::table('LOAIKHACHHANG')->where('TENLOAI', 'Học sinh, Sinh viên')->update(['REQUIRES_VERIFICATION' => true]);

        Schema::create('gym_invoice_discounts', function (Blueprint $table): void {
            $table->id();
            $table->decimal('invoice_id', 18, 0);
            $table->string('source_type', 40);
            $table->decimal('tier_id', 18, 0)->nullable();
            $table->string('tier_name', 100)->nullable();
            $table->unsignedTinyInteger('rate_percent');
            $table->unsignedBigInteger('base_amount');
            $table->unsignedBigInteger('discount_amount');
            $table->timestamp('created_at');
            $table->unique(['invoice_id', 'source_type']);
            $table->index('invoice_id');
        });

        Schema::create('gym_customer_tier_verifications', function (Blueprint $table): void {
            $table->id();
            $table->decimal('customer_id', 18, 0);
            $table->decimal('tier_id', 18, 0);
            $table->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $table->string('reason', 255);
            $table->timestamp('verified_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'tier_id', 'revoked_at'], 'gctv_customer_tier_revoked_idx');
        });

        DB::unprepared('DROP PROCEDURE IF EXISTS SP_KHACHHANGDANGKY');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE SP_KHACHHANGDANGKY(
    IN p_TenKH       VARCHAR(150),
    IN p_GioiTinh    VARCHAR(10),
    IN p_NgaySinh    DATETIME,
    IN p_SDT         VARCHAR(20),
    IN p_Email       VARCHAR(100),
    IN p_TenDangNhap VARCHAR(50),
    IN p_MatKhau     VARCHAR(100)
)
BEGIN
    DECLARE v_MaLoaiKHMoi DECIMAL(18,0);
    DECLARE v_NewID DECIMAL(18,0);
    DECLARE v_MatKhauEnc VARCHAR(100);
    DECLARE v_EmailEnc VARCHAR(100) DEFAULT NULL;
    DECLARE v_SDTEnc VARCHAR(20) DEFAULT NULL;

    SET v_MatKhauEnc = f_encryptAddCipher(p_MatKhau, 6);
    IF p_Email IS NOT NULL THEN SET v_EmailEnc = f_encryptAddCipher(p_Email, 6); END IF;
    IF p_SDT IS NOT NULL THEN SET v_SDTEnc = f_encryptAddCipher(p_SDT, 6); END IF;

    IF EXISTS (SELECT 1 FROM KHACHHANG WHERE TENDANGNHAP = p_TenDangNhap) THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 50001,
            MESSAGE_TEXT = 'Tên đăng nhập này đã được sử dụng. Vui lòng chọn tên khác.';
    END IF;
    IF v_SDTEnc IS NOT NULL AND EXISTS (SELECT 1 FROM KHACHHANG WHERE SDT = v_SDTEnc) THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 50002,
            MESSAGE_TEXT = 'Số điện thoại này đã được đăng ký cho một tài khoản khác.';
    END IF;
    IF v_EmailEnc IS NOT NULL AND EXISTS (SELECT 1 FROM KHACHHANG WHERE EMAIL = v_EmailEnc) THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 50003,
            MESSAGE_TEXT = 'Email này đã được đăng ký cho một tài khoản khác.';
    END IF;

    SELECT MALOAIKH INTO v_MaLoaiKHMoi FROM LOAIKHACHHANG WHERE TENLOAI = 'Gym Null' LIMIT 1;
    SELECT IFNULL(MAX(MAKH), 0) + 1 INTO v_NewID FROM KHACHHANG;
    INSERT INTO KHACHHANG (MAKH, TENKH, GIOITINH, NGAYSINH, SDT, EMAIL, TENDANGNHAP, MATKHAU, MALOAIKH)
    VALUES (v_NewID, p_TenKH, p_GioiTinh, p_NgaySinh, v_SDTEnc, v_EmailEnc, p_TenDangNhap, v_MatKhauEnc, v_MaLoaiKHMoi);
    SELECT v_NewID AS NewMaKH;
END
SQL);
    }

    public function down(): void
    {
        throw new LogicException('Không tự xóa ảnh chụp giao dịch hoặc lịch sử xác minh. Tạo migration sửa tiếp để bảo toàn dữ liệu.');
    }
};
