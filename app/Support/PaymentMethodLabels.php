<?php

namespace App\Support;

class PaymentMethodLabels
{
    private const LABELS = [
        'qris_full' => 'Pembayaran Online - Lunas',
        'qris_dp' => 'Pembayaran Online - DP 50%',
        'cash' => 'Tunai',
    ];

    public static function label(?string $paymentMethod, string $fallback = '-'): string
    {
        if ($paymentMethod === null || $paymentMethod === '') {
            return $fallback;
        }

        return self::LABELS[$paymentMethod] ?? ucfirst(str_replace('_', ' ', $paymentMethod));
    }

    public static function options(): array
    {
        return self::LABELS;
    }
}