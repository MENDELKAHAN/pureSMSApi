# 📲 PureSMS API Laravel Package

This Laravel package provides a **simple and reusable integration** with the **PureSMS API**.  
It allows you to **send SMS messages**, **handle webhooks**, and **store SMS logs in the database**.

---

## 🚀 Features
✅ Send **single** and **bulk** SMS messages  
✅ Store **SMS logs** in the database  
✅ Handle **webhooks** to update delivery status  
✅ Works with **Laravel's service provider and facade**  
✅ Installable via **Composer**

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


Set up your API key and endpoint in .env:
```bash

PURESMS_API_KEY=your_api_key_here
PURESMS_ENDPOINT=https://connect-api.divergent.cloud
```

📤 Sending SMS

You can send an SMS using the PureSms facade:
```bash

use Puresms\Laravel\Facades\PureSms;
$response = PureSms::sendSms('+447123456789', 'Hello from Laravel!');
```

📥 Handling Webhooks









```bash

#use App\Http\Controllers\SmsController;
#Route::post('/puresms-webhook', [SmsController::class, 'handleWebhook']);

Route::puresmsWebhooks('puresms-webhook');
```
## 📊 Database Logging

All sent messages are **automatically stored** in the database in the `sms_logs` table.

| Column        | Type       | Description                                |
|--------------|-----------|--------------------------------------------|
| `id`         | bigint    | Auto-incrementing ID                      |
| `message_id` | string    | Unique message ID from PureSMS            |
| `recipient`  | string    | Recipient's phone number                  |
| `sender`     | string    | Sender name or number                     |
| `content`    | text      | SMS message content                       |
| `status`     | string    | `pending`, `sent`, `delivered`, etc.      |
| `error_code` | integer   | Error code (if any)                       |
| `processed_at` | timestamp | When it was processed                  |
| `delivered_at` | timestamp | When it was delivered                  |



🎯 Example Usage
Send an SMS & Check Delivery Status
```bash

use Puresms\Laravel\Facades\PureSms;
use Puresms\Laravel\Models\SmsLog;

// Send an SMS
$sms = PureSms::sendSms('+447123456789', 'Test message');

// Check SMS Status in the database
$log = SmsLog::where('message_id', $sms['id'])->first();
echo 'Status: ' . $log->status;
```
🛠️ Updating the Package
```bash

composer update mendelkahan/puresmsapi
php artisan migrate
```


using Bulk Sms example

Open tinker
```bash
php artisan tinker
```

this is based that users have sms_number
```bash
$puresms = app(Puresms\Laravel\PureSmsService::class);

$users = User::get(); 

$messages = $users->map(function ($user) {
    return [
        'sender' => 'ConnectTest',
        'recipient' => $user->sms_number ?? "", 
        'content' => "Hello {$user->name}, 
    ];
})->filter(fn ($msg) => $msg['recipient'] !== null)->toArray();

$response = $puresms->sendSmsBatch($messages);
```

