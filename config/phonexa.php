<?php

declare(strict_types=1);

// Remove trailing /lead
$url = (string) env('PHONEXA_LMS_SYNC_BASE_URL', '');
$url = (string) preg_replace('#/lead/*$#', '', $url);
$url = rtrim($url, '/');

return [

    'lms-sync' => [

        /*
         * Instance specific. Note this is the `leads-` host, not the `cp-` host
         * the API documentation itself is served from.
         * ex: https://leads-inst1-client.phonexa.com
         */
        'base-url' => $url,

        'api-id'       => env('PHONEXA_LMS_SYNC_API_ID', ''),
        'api-password' => env('PHONEXA_LMS_SYNC_API_PASSWORD', ''),

        /*
         * Applied to posted leads that do not name a product of their own. Leave
         * null when the application posts to more than one Phonexa product.
         */
        'product-id' => env('PHONEXA_LMS_SYNC_PRODUCT_ID'),

        /*
         * Adds testMode=1 to every posted lead so it is validated but never sold
         * for real. Enable in local and staging environments; never in production.
         */
        'test-mode' => env('PHONEXA_LMS_SYNC_TEST_MODE', false),
    ],

];
