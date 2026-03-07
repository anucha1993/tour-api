<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | [DEV MODE] อนุญาตทุก origin - ใช้สำหรับ development เท่านั้น
    | [PRODUCTION] เมื่อพร้อม deploy ให้ comment บรรทัด '*' และ uncomment รายการด้านล่าง
    |
    */

    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'https://nexttrip.asia',
        'https://www.nexttrip.asia',
        'https://admin.nexttrip.asia',
        'https://backend.nexttrip.asia',
        'https://nexttrip.world',
        'https://www.nexttrip.world',
        'https://admin.nexttrip.world',
        'https://backend.nexttrip.world',
    ],

    'allowed_origins_patterns' => [
        '#^https://.*\.nexttrip\.asia$#',
        '#^https://.*\.nexttrip\.world$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
