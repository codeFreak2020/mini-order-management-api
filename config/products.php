<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product cache TTL
    |--------------------------------------------------------------------------
    |
    | Number of seconds a single-product lookup (GET /api/products/{id}) is
    | cached before it is refreshed from the database.
    |
    */

    'cache_ttl' => (int) env('PRODUCT_CACHE_TTL', 300),

];
