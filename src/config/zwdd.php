<?php

return [
    'app_server' => env('ZWDD_APP_SERVER', ''),
    'app_key' => env('ZWDD_APP_KEY', ''),
    'app_secret' => env('ZWDD_APP_SECRET', ''),
    'scan_app_key' => env('ZWDD_SCAN_APP_KEY', env('ZWDD_APP_KEY', '')),
    'scan_app_secret' => env('ZWDD_SCAN_APP_SECRET', env('ZWDD_APP_SECRET', '')),
];