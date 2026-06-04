<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public Media URL Mode
    |--------------------------------------------------------------------------
    |
    | "direct" returns the raw storage-backed URL (current behavior).
    | "proxy" returns an opaque application route like /media/{public_key}/...
    |
    */
    'public_url_mode' => env('MEDIA_PUBLIC_URL_MODE', 'direct'),

    /*
    |--------------------------------------------------------------------------
    | Proxy Delivery Mode
    |--------------------------------------------------------------------------
    |
    | "stream" serves the file through the app route.
    | "redirect" resolves the opaque key, then redirects to the direct file URL.
    |
    */
    'proxy_delivery_mode' => env('MEDIA_PROXY_DELIVERY_MODE', 'redirect'),

    /*
    |--------------------------------------------------------------------------
    | Public Media Route Prefix
    |--------------------------------------------------------------------------
    */
    'route_prefix' => env('MEDIA_PUBLIC_ROUTE_PREFIX', 'media'),

    /*
    |--------------------------------------------------------------------------
    | Cache-Control Max Age
    |--------------------------------------------------------------------------
    */
    'cache_max_age' => (int) env('MEDIA_PUBLIC_CACHE_MAX_AGE', 604800),
];
