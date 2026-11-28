<?php

use App\Services\Payment\Gateways\MockGateway;

return [
    'default' => env('PAYMENT_GATEWAY', 'mock'),

    /*
    | Each driver implements App\Services\Payment\Contracts\PaymentGatewayInterface.
    | Add SSLCommerz / bKash / Nagad / Stripe drivers here; secrets come from env only.
    */
    'gateways' => [
        'mock' => [
            'driver' => MockGateway::class,
            'enabled' => (bool) env('PAYMENT_MOCK_ENABLED', ! in_array(env('APP_ENV'), ['production'], true)),
            'secret' => env('PAYMENT_MOCK_SECRET', env('APP_KEY')),
        ],
    ],

    // Where the buyer lands after the gateway flow (SPA routes).
    'return_url' => env('FRONTEND_URL', 'http://localhost:5173').'/orders/{order}/payment-result',
];
