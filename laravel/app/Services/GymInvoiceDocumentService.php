<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GymInvoiceDocumentService
{
    public function __construct(private OriginalGymSql $sql, private GymCommerceService $commerce) {}

    public function sign(int $id, User $staff): void
    {
        abort_unless($staff->role === 'manager', 403);
        $this->sql->transaction(function () use ($id): void {
            $invoice = $this->commerce->invoice($id);
            if ($invoice->TRANGTHAI !== 'Đã thanh toán') {
                throw ValidationException::withMessages(['invoice' => 'Chỉ ký hóa đơn đã thanh toán.']);
            }
            $this->commerce->assertInvoiceHasDetails($id);
            if ($invoice->SIGNATURE_PATH && Storage::disk('local')->exists($invoice->SIGNATURE_PATH)) {
                return;
            }
            $keyPath = 'gym-signing/private.pem';
            if (! Storage::disk('local')->exists($keyPath)) {
                $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
                if (config('gym.openssl_config')) {
                    $options['config'] = config('gym.openssl_config');
                }
                $key = openssl_pkey_new($options);
                if (! $key || ! openssl_pkey_export($key, $private, null, $options)) {
                    throw new \RuntimeException('Không tạo được khóa RSA.');
                }
                Storage::disk('local')->put($keyPath, $private);
            }
            $key = openssl_pkey_get_private(Storage::disk('local')->get($keyPath));
            $public = openssl_pkey_get_details($key)['key'];
            $sets = $this->sql->call('sp_LayChiTietHoaDon', [$id]);
            $snapshots = DB::table('gym_invoice_lines')->where('invoice_id', $id)->orderBy('id')->get();
            if ($snapshots->isNotEmpty()) {
                $sets[1] = $snapshots->map(fn ($line) => (object) ['TENHANG' => $line->name, 'SOLUONG' => $line->quantity, 'DONGIA' => $line->unit_price, 'THANHTIEN_SP' => $line->quantity * $line->unit_price])->all();
            }
            $content = view('gym.invoice-document', ['invoice' => $invoice, 'lines' => $sets[1] ?? []])->render();
            if (! openssl_sign($content, $signature, $key, OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('Không ký được chứng từ.');
            }
            $path = 'gym-invoices/'.$id.'.html';
            $signaturePath = 'gym-invoices/'.$id.'.sig';
            Storage::disk('local')->put($path, $content);
            Storage::disk('local')->put($signaturePath, $signature);
            DB::table('HOADON')->where('MAHD', $id)->update(['FILE_PATH' => $path, 'SIGNATURE_PATH' => $signaturePath, 'PUBLIC_KEY_USED' => $public, 'SIGNATURE_VERIFIED' => true, 'VERIFIED_AT' => now()]);
            $this->sql->audit('Ký hóa đơn', 'HOADON:'.$id);
        });
    }

    public function verify(int $id, User $user, ?string $content = null): bool
    {
        $invoice = $this->commerce->invoice($id, $user);
        if (! $invoice->PUBLIC_KEY_USED || ! $invoice->SIGNATURE_PATH || ! $invoice->FILE_PATH || ! Storage::disk('local')->exists($invoice->SIGNATURE_PATH) || ! Storage::disk('local')->exists($invoice->FILE_PATH)) {
            throw ValidationException::withMessages(['document' => 'Chưa có đủ tệp và chữ ký. Hãy tạo chứng từ đã ký trước.']);
        }
        $uploaded = $content !== null;
        $content ??= Storage::disk('local')->get($invoice->FILE_PATH);
        $valid = openssl_verify($content, Storage::disk('local')->get($invoice->SIGNATURE_PATH), $invoice->PUBLIC_KEY_USED, OPENSSL_ALGO_SHA256) === 1;
        if (! $uploaded) {
            DB::table('HOADON')->where('MAHD', $id)->update(['SIGNATURE_VERIFIED' => $valid, 'VERIFIED_AT' => now()]);
        }
        $this->sql->audit('Kiểm tra chữ ký', 'HOADON:'.$id, ['valid' => $valid, 'uploaded' => $uploaded]);

        return $valid;
    }
}
