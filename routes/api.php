<?php

use Illuminate\Support\Facades\Route;
use Puresms\Laravel\Http\Controllers\SmsController;
use Puresms\Laravel\Http\Controllers\WebhookController;


Route::get('/send-sms', [SmsController::class, 'sendSms']);
Route::post('/puresms-webhook', [WebhookController::class, 'handleWebhook']);


