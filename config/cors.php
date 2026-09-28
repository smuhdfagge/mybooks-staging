<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration determines what cross-origin operations may execute
    | in web browsers. Configure for mobile app API access.
    |
    */

    /*
    | Paths that should have CORS headers applied.
    | The api/* pattern ensures all API routes are accessible.
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    /*
    | Allowed HTTP methods for CORS requests.
    | Mobile apps typically need all standard methods.
    */
    'allowed_methods' => ['*'],

    /*
    | Origins that are allowed to make requests.
    |
    | For development: Use '*' or specific localhost URLs
    | For production: Replace with your mobile app's origins
    |
    | Mobile apps using capacitor/cordova may use:
    | - capacitor://localhost
    | - ionic://localhost
    | - http://localhost (for web preview)
    |
    | React Native and Flutter don't typically need CORS as they
    | make native HTTP requests, but web versions do.
    */
    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost,http://localhost:3000,http://127.0.0.1:8000'))),

    /*
    | Patterns for allowed origins (regex supported).
    | Useful for allowing multiple subdomains.
    */
    'allowed_origins_patterns' => [
        // Example: Allow all subdomains of my-books.cloud
        // '#^https://.*\.my-books\.cloud$#',
    ],

    /*
    | Headers that are allowed in CORS requests.
    | '*' allows all headers which is needed for Authorization tokens.
    */
    'allowed_headers' => ['*'],

    /*
    | Headers that should be exposed to the browser/client.
    | These headers can be accessed by the mobile app.
    */
    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
        'X-API-Version',
        'X-API-Deprecated',
    ],

    /*
    | Maximum age (in seconds) for the preflight request cache.
    | Mobile apps can cache preflight responses for better performance.
    */
    'max_age' => 86400, // 24 hours

    /*
    | Whether to support credentials (cookies, authorization headers).
    | Enable in production only when allowed_origins is set to specific domains.
    */
    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),

];
