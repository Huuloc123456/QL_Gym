<?php

namespace App\Services;

class VietQrService
{
    public function previewImageUrl(?object $settings): ?string
    {
        $bankCode = strtolower(trim((string) $settings?->bank_code));
        $account = preg_replace('/\s+/', '', (string) $settings?->bank_account);
        $accountName = trim((string) ($settings?->bank_account_name ?? $settings?->bank_holder));

        if ($bankCode === '' || ! preg_match('/^[a-z0-9]{2,20}$/', $bankCode)
            || $account === '' || $accountName === '') {
            return null;
        }

        $path = rawurlencode($bankCode).'-'.rawurlencode($account).'-compact2.png';

        return 'https://img.vietqr.io/image/'.$path.'?'.http_build_query([
            'accountName' => $accountName,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function imageUrl(?object $settings, int $orderId, int $amount): ?string
    {
        return $this->buildImageUrl($settings, $amount, 'GYM-'.$orderId);
    }

    public function invoiceImageUrl(?object $settings, int $invoiceId, int $amount): ?string
    {
        return $this->buildImageUrl($settings, $amount, 'GYM HD'.$invoiceId);
    }

    private function buildImageUrl(?object $settings, int $amount, string $memo): ?string
    {
        $bankCode = strtolower(trim((string) $settings?->bank_code));
        $account = preg_replace('/\s+/', '', (string) $settings?->bank_account);
        $accountName = trim((string) ($settings?->bank_account_name ?? $settings?->bank_holder));

        if ($bankCode === '' || ! preg_match('/^[a-z0-9]{2,20}$/', $bankCode)
            || $account === '' || $accountName === '' || $amount < 1) {
            return null;
        }

        $path = rawurlencode($bankCode).'-'.rawurlencode($account).'-compact2.png';
        $query = http_build_query([
            'amount' => $amount,
            'addInfo' => $memo,
            'accountName' => $accountName,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'https://img.vietqr.io/image/'.$path.'?'.$query;
    }
}
