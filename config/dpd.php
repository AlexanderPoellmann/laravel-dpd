<?php

return [
    'endpoint' => env('DPD_ENDPOINT', 'https://ws.paketomat.at/restapi106/service.php'),

    'username' => env('DPD_USERNAME'),
    'client' => env('DPD_CLIENT'),

    // Supply either the plain password or the MD5 digest issued by DPD.
    // DPD_PASSWORD_MD5 takes precedence when both are configured.
    'password' => env('DPD_PASSWORD'),
    'password_md5' => env('DPD_PASSWORD_MD5'),

    'timeout' => (int) env('DPD_TIMEOUT', 30),
    'connect_timeout' => (int) env('DPD_CONNECT_TIMEOUT', 10),
    'retries' => (int) env('DPD_RETRIES', 2),
    'retry_delay_ms' => (int) env('DPD_RETRY_DELAY_MS', 250),
];
