<?php

return [
    'base_url' => env('PANEL_BASE_URL', ''),
    'jwt_token' => env('PANEL_JWT_TOKEN', ''),
    'default_server_id' => env('PANEL_DEFAULT_SERVER_ID') ? (int) env('PANEL_DEFAULT_SERVER_ID') : null,
    'http_timeout_seconds' => 30,
];
