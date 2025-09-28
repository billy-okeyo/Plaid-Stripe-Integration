<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = true; // ✅ keep if you need the cookie for JS apps

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * You can use wildcards (*).
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/docs/*',                 // Exclude all Swagger UI endpoints
        'csrf-token',                 // GET /csrf-token
        'webhook/*',                  // Any webhooks
        'api/plaid/create-ach-transfer',
        'api/stripe/check-payment-intent',
    ];
}
