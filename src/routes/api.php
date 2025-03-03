<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SmsController;


Route::get('/send-sms', [SmsController::class, 'sendSms']);
Route::post('/send-bulk-sms', [SmsController::class, 'sendBulkSms']);
Route::get('/sms-balance', [SmsController::class, 'getBalance']);
Route::delete('/cancel-sms', [SmsController::class, 'cancelSms']);
Route::delete('/cancel-bulk-sms', [SmsController::class, 'cancelBulkSms']);

Route::post('/puresms-webhook', [SmsController::class, 'handleWebhook']);

