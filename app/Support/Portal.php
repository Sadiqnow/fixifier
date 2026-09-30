<?php

namespace App\Support;

class Portal
{
    public const LABELS = ['requested' => 'Unassigned', 'awaiting_quote' => 'Awaiting quote', 'awaiting_customer' => 'Quote sent', 'awaiting_payment' => 'Payment pending', 'confirmed' => 'Quote accepted', 'in_progress' => 'In progress', 'awaiting_verification' => 'Customer review', 'disputed' => 'Disputed', 'rework_required' => 'Rework required', 'release_pending' => 'Payout pending', 'refund_pending' => 'Refund pending', 'completed' => 'Completed', 'refunded' => 'Refunded', 'cancelled' => 'Cancelled'];

    public static function money(int|float $amount): string
    {
        return '₦'.rtrim(rtrim(number_format($amount, 2, '.', ','), '0'), '.');
    }

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function color(string $status): string
    {
        return in_array($status, ['approved', 'completed', 'published']) ? 'green' : (in_array($status, ['disputed', 'suspended', 'rejected', 'refunded']) ? 'red' : (in_array($status, ['requested', 'pending_review', 'awaiting_quote', 'rework_required', 'hidden']) ? 'orange' : ''));
    }
}
