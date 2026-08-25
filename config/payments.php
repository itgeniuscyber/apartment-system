<?php

return [
    'driver' => env('PAYMENT_GATEWAY_DRIVER'),
    'display_name' => env('PAYMENT_GATEWAY_NAME', 'Online payment gateway'),
    'webhook_secret' => env('PAYMENT_GATEWAY_WEBHOOK_SECRET'),
    'checkout_ready' => (bool) env('PAYMENT_GATEWAY_CHECKOUT_READY', false),
    'payment_link_expiry_days' => (int) env('PAYMENT_LINK_EXPIRY_DAYS', 14),
    'methods' => ['mobile_money', 'bank_transfer', 'card'],
];
