<?php

return [
    // Test keys only until custody, reserve release and production settlement are certified.
    'payments_enabled' => env('JOURNEY_PAYMENTS_ENABLED', false),
    'paystack_secret' => env('PAYSTACK_SECRET_KEY'),
    'fees_approved' => env('JOURNEY_FEES_APPROVED', false),
];
