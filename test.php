<?php

require __DIR__ . '/vendor/autoload.php'; // Load dependencies

use Puresms\Laravel\PureSmsService;
// Create an instance of PureSmsService
$puresms = new PureSmsService();

// Test sending an SMS
$response = $puresms->sendSms('1234567890', 'Test Message');

// Print response
var_dump($response);
