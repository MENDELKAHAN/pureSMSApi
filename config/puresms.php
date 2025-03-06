<?php

return [
    'api_key' => env('PURESMS_API_KEY'),
    'endpoint' => env('PURESMS_ENDPOINT', 'https://connect-api.divergent.cloud'),
    'puresms' => env('PURESMS_SENDER', 'ConnectTest'),


    'model' => \mendelkahan\LaravelPuresms\Models\SmsLog::class
,
];
