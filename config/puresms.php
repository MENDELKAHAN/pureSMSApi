<?php

use App\Models\User;
use Puresms\Laravel\Models\SmsLog;

return [
    'api_key' => env('PURESMS_API_KEY'),
    'endpoint' => env('PURESMS_ENDPOINT', 'https://connect-api.divergent.cloud'),
    'sender' => env('PURESMS_SENDER', 'ConnectTest'),
    'model' => SmsLog::class,
    'number_model' => env('PURESMS_NUMBER_MODEL', User::class),
    'mobile_number' => env('PURESMS_MOBILE_NUMBER', 'sms_number'),
    'timeout' => 20,
    'connect_timeout' => 5,
    'webhook_secret' => env('PURESMS_WEBHOOK_SECRET'),
    'webhook_tolerance' => 300,
];
