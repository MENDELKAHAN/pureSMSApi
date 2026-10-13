# 📲 PureSMS API Laravel Package

This Laravel package provides a **simple and reusable integration** with the **PureSMS API**.  
It allows you to **send SMS messages**, **handle webhooks**, and **store SMS logs in the database**.

---

## 🚀 Features
✅ Send **single** and **bulk** SMS messages  
✅ Store **SMS logs** in the database  
✅ Handle **webhooks** to update delivery status and handle inbound SMS  
✅ Works with **Laravel's service provider and facade**  
✅ Installable via **Composer**  
✅ Configurable model and phone number field for flexible integration  

---

## 📦 Installation

First, install the package via Composer:

```bash
composer require mendelkahan/puresmsapi
```

Then, publish the configuration and migration files:

```bash
php artisan vendor:publish --tag=puresms-config
php artisan vendor:publish --tag=puresms-migrations
php artisan migrate
```

Set up your API key, endpoint, and optional model configuration in `.env`:

```bash
PURESMS_API_KEY=your_api_key_here
PURESMS_ENDPOINT=https://connect-api.divergent.cloud
PURESMS_SENDER=ConnectTest
PURESMS_NUMBER_MODEL=App\Models\User
PURESMS_MOBILE_NUMBER=sms_number
```

> **Note**: The `PURESMS_NUMBER_MODEL` and `PURESMS_MOBILE_NUMBER` are optional. By default, the package uses the `User` model and `sms_number` field. You can set these to another model (e.g., `App\Models\People`) and field (e.g., `phone_number`) for projects where phone numbers are stored differently.

---

## 📤 Sending SMS

You can send an SMS using the `PureSms` facade:

```bash
use Puresms\Laravel\Facades\PureSms;

$response = PureSms::sendSms('+447123456789', 'Hello from Laravel!');
```

### Bulk SMS Example

Send SMS to multiple recipients using a configurable model (e.g., `People`):

```bash
use Puresms\Laravel\Facades\PureSms;
use App\Models\People;

$puresms = app(Puresms\Laravel\PureSmsService::class);
$people = People::get();

$messages = $people->map(function ($person) {
    return [
        'sender' => 'ConnectTest',
        'recipient' => $person->phone_number ?? '',
        'content' => "Hello {$person->name}, welcome!",
    ];
})->filter(fn ($msg) => !empty($msg['recipient']))->toArray();

$response = $puresms->sendSmsBatch($messages);
```

---

## 📥 Handling Webhooks

Set up a route to handle PureSMS webhooks:

```bash
use App\Http\Controllers\SmsController;

Route::post('/puresms-webhook', [SmsController::class, 'handleWebhook']);

// Or use the provided route macro
Route::puresmsWebhooks('puresms-webhook');
```

The package automatically processes delivery status updates and inbound SMS, linking them to the configured model (e.g., `User` or `People`) based on the phone number.

---

## 📊 Database Logging

All sent and received messages are **automatically stored** in the `sms_logs` table.

| Column          | Type       | Description                                |
|-----------------|------------|--------------------------------------------|
| `id`            | bigint     | Auto-incrementing ID                      |
| `message_id`    | string     | Unique message ID from PureSMS            |
| `recipient`     | string     | Recipient's phone number                  |
| `sender`        | string     | Sender name or number                     |
| `recipient_id`  | int        | Recipient's ID from configured model      |
| `sender_id`     | int        | Sender's ID from configured model         |
| `content`       | text       | SMS message content                       |
| `status`        | string     | `pending`, `sent`, `delivered`, `received`, etc. |
| `error_code`    | integer    | Error code (if any)                       |
| `processed_at`  | timestamp  | When it was processed                     |
| `delivered_at`  | timestamp  | When it was delivered                     |

---

## 🎯 Example Usage

### Send an SMS & Check Delivery Status

```bash
use Puresms\Laravel\Facades\PureSms;
use Puresms\Laravel\Models\SmsLog;

// Send an SMS
$sms = PureSms::sendSms('+447123456789', 'Test message');

// Check SMS Status in the database
$log = SmsLog::where('message_id', $sms['message_id'])->first();
echo 'Status: ' . $log->status;
```

### Using Tinker for Bulk SMS

Open Tinker:

```bash
php artisan tinker
```

Example with a `People` model (assuming `phone_number` field):

```bash
$puresms = app(Puresms\Laravel\PureSmsService::class);
$people = App\Models\People::get();

$messages = $people->map(function ($person) {
    return [
        'sender' => 'ConnectTest',
        'recipient' => $person->phone_number ?? '',
        'content' => "Hello {$person->name}, welcome!",
    ];
})->filter(fn ($msg) => !empty($msg['recipient']))->toArray();

$response = $puresms->sendSmsBatch($messages);
```

---

## 🛠️ Updating the Package

To update to the latest version:

```bash
composer update mendelkahan/puresmsapi
php artisan migrate
```

---

## ⚙️ Configuration

The configuration file (`config/puresms.php`) allows you to customize the package:

```php
return [
    'api_key' => env('PURESMS_API_KEY', ''),
    'endpoint' => env('PURESMS_ENDPOINT', 'https://connect-api.divergent.cloud'),
    'sender' => env('PURESMS_SENDER', 'ConnectTest'),
    'number_model' => env('PURESMS_NUMBER_MODEL', \App\Models\User::class),
    'mobile_number' => env('PURESMS_MOBILE_NUMBER', 'sms_number'),
];
```

- `number_model`: The Eloquent model containing phone numbers (default: `App\Models\User`).
- `mobile_number`: The field name for the phone number in the model (default: `sms_number`).

For example, to use a `People` model with a `phone_number` field, set in `.env`:

```bash
PURESMS_NUMBER_MODEL=App\Models\People
PURESMS_MOBILE_NUMBER=phone_number
```

This ensures compatibility with existing projects using the `User` model while allowing flexibility for new setups.