<?php

namespace App\Services;

class EarningsCalculator
{
    // All persisted and returned values are integer kobo, rounded once per fee component.
    public function calculate(int $gross, array $s): array
    {
        $platform = (int) round($gross * $s['feePercent'] / 100) + (int) round($s['feeFixed'] * 100);
        $provider = (int) round($gross * $s['providerFeePercent'] / 100) + (int) round($s['providerFeeFixed'] * 100);
        $reserve = (int) round($gross * $s['reservePercent'] / 100);
        $net = $gross - $platform - $reserve - ($s['providerFeePayer'] === 'technician' ? $provider : 0);

        return ['gross' => $gross, 'platform' => $platform, 'provider' => $provider, 'reserve' => $reserve,
            'net' => max(0, $net), 'platformNet' => $platform - ($s['providerFeePayer'] === 'platform' ? $provider : 0), 'valid' => $net >= 0];
    }
}
