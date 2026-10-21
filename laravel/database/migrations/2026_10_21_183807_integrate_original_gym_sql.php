<?php

use App\Services\OriginalGymSql;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(OriginalGymSql::class)->import();
        Schema::create('gym_write_lock', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
        });
        DB::table('gym_write_lock')->insert(['id' => 1]);
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->string('phone', 20)->nullable()->change();
            $table->decimal('sql_customer_id', 18, 0)->nullable()->unique();
            $table->decimal('sql_employee_id', 18, 0)->nullable()->unique();
        });
        foreach (['gym_packages' => 'sql_package_id', 'membership_orders' => 'sql_invoice_id',
            'memberships' => 'sql_membership_id', 'check_ins' => 'sql_checkin_id', 'trial_requests' => 'sql_membership_id'] as $name => $column) {
            Schema::table($name, function (Blueprint $table) use ($column): void {
                $table->decimal($column, 18, 0)->nullable()->unique();
            });
        }
        Schema::table('check_ins', function (Blueprint $table): void {
            $table->unsignedBigInteger('membership_id')->nullable()->change();
        });
        Schema::create('gym_fulfillments', function (Blueprint $table): void {
            $table->decimal('invoice_id', 18, 0)->primary();
            $table->string('mode', 20);
            $table->string('state', 30)->default('pending');
            $table->string('payment_method', 20);
            $table->string('recipient', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->json('address')->nullable();
            $table->unsignedBigInteger('shipping_fee')->default(0);
            $table->boolean('cancellation_requested')->default(false);
            $table->string('checkout_token', 36)->unique();
            $table->timestamps();
        });
        Schema::create('gym_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->decimal('invoice_id', 18, 0)->index();
            $table->string('kind', 20);
            $table->decimal('item_id', 18, 0);
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->json('details');
        });
        Schema::create('gym_receipts', function (Blueprint $table): void {
            $table->id();
            $table->decimal('invoice_id', 18, 0)->index();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->string('token', 36)->unique();
            $table->timestamp('created_at');
        });
        Schema::create('gym_pt_rates', function (Blueprint $table): void {
            $table->decimal('employee_id', 18, 0)->primary();
            $table->unsignedBigInteger('price');
        });
        Schema::create('gym_action_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('action', 100);
            $table->string('subject', 100);
            $table->json('data');
            $table->timestamp('created_at')->index();
        });
        foreach (['KHACHHANG' => 'MAKH', 'NHANVIEN' => 'MANV'] as $table => $key) {
            foreach (DB::table($table)->get() as $row) {
                DB::table($table)->where($key, $row->$key)->update(['MATKHAU' => Hash::make($row->MATKHAU)]);
            }
        }
        foreach (DB::table('DANGKYPT')->select('MANV')->selectRaw('MAX(GIAMOIBUOI) AS price')->groupBy('MANV')->get() as $rate) {
            DB::table('gym_pt_rates')->insert(['employee_id' => $rate->MANV, 'price' => $rate->price]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new LogicException('Không tự xóa dữ liệu SQL gốc khi rollback. Khôi phục bản sao lưu hoặc dùng migration sửa tiếp.');
    }
};
