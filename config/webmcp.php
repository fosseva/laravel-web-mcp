<?php

return [
    'enabled' => env('WEBMCP_ENABLED', true),
    'path' => '_webmcp',
    // Session and CSRF protection are required. Add auth / throttling as needed.
    'middleware' => ['web'],
    // Reload the Blade page after this many minutes to renew its tool exposures.
    'exposure_ttl' => 60,
];
