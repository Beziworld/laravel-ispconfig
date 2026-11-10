<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default connection
    |--------------------------------------------------------------------------
    |
    | Name of the connection used by the ISPConfig facade when no connection
    | is selected explicitly with ISPConfig::connection().
    |
    */

    'default' => env('ISPCONFIG_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | "url" is the panel address, e.g. https://panel.example.com:8080. The
    | JSON endpoint /remote/json.php is appended when the URL has no path.
    | Port 8080 is assumed when the URL has no port.
    |
    | "username" and "password" belong to a remote user created under
    | System > Remote Users, with "Remote Access" restricted to the address
    | of the application and only the functions it needs enabled.
    |
    | "session_ttl" is the number of seconds a remote session ID is reused.
    | ISPConfig ends a session 1800 seconds after the login, so the value must
    | stay below that. "cache_store" selects the Laravel cache store holding
    | the session ID (null: the default store).
    |
    | "verify_ssl" may be true, false or a path to a CA bundle. Disable it
    | only for panels with self-signed certificates on trusted networks.
    |
    */

    'connections' => [

        'default' => [
            'url' => env('ISPCONFIG_URL'),
            'username' => env('ISPCONFIG_USERNAME'),
            'password' => env('ISPCONFIG_PASSWORD'),
            'verify_ssl' => env('ISPCONFIG_VERIFY_SSL', true),
            'timeout' => (int) env('ISPCONFIG_TIMEOUT', 60),
            'connect_timeout' => (int) env('ISPCONFIG_CONNECT_TIMEOUT', 10),
            'session_ttl' => (int) env('ISPCONFIG_SESSION_TTL', 1500),
            'cache_store' => env('ISPCONFIG_CACHE_STORE'),
        ],

    ],

];
