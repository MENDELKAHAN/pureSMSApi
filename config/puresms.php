<?php

return [
    'api_key' => env('PURESMS_API_KEY'),
    'endpoint' => env('PURESMS_ENDPOINT', 'https://connect-api.divergent.cloud'),
    'puresms' => env('PURESMS_SENDER', 'ConnectTest'),
    'model' => \mendelkahan\LaravelPuresms\Models\SmsLog::class,

    'number_model' => env('PURESMS_NUMBER_MODEL', \App\Models\User::class),
    'mobile_number' => env('PURESMS_MOBILE_NUMBER', 'sms_number'),
];
