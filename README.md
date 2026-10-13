# PureSMS for Laravel

Send SMS through PureSMS and store send/delivery logs in Laravel 10–13. Laravel's own PHP requirement applies (Laravel 13 requires PHP 8.3 or later).

## Installation

```sh
composer require mendelkahan/laravel-puresms:^1.1.4
php artisan vendor:publish --tag=puresms-config
php artisan vendor:publish --tag=puresms-migrations
php artisan migrate
```

Configure the application environment; never commit account credentials:

```dotenv
PURESMS_API_KEY=your_api_key
PURESMS_ENDPOINT=https://connect-api.divergent.cloud
PURESMS_SENDER=your_approved_sender
PURESMS_NUMBER_MODEL=App\Models\User
PURESMS_MOBILE_NUMBER=sms_number
```

The sender is `config('puresms.sender')`. A previously published configuration may contain the old, incorrect `puresms` key: rename it to `sender`. Rebuild your application's configuration cache after changing settings. `config/puresms.php` also supports HTTP `timeout` (20 seconds), `connect_timeout` (5 seconds), and a configurable SMS log `model` (default `Puresms\Laravel\Models\SmsLog`). API endpoints must use HTTPS. Redirects are disabled to protect the API key.

The package does **not register sending or webhook routes automatically**. Keep sending behind your own authenticated, authorized application actions. Applications upgrading from 1.1.3 must replace any dependency on the old public `/send-sms` route.

## Send one SMS

```php
use Puresms\Laravel\Facades\PureSms;

$result = PureSms::sendSms('+447123456789', 'Your repair is ready.', 'YourSender');
```

The signature remains `sendSms($to, $message, $from = null, $recipientId = null, $senderId = null)`. It posts `sender`, `recipient`, and `content` to `/sms/send` with an `X-API-Key` header.

The result has `status` (`sent`, `failed`, or `unknown`), `message_id`, and `error`. A valid success response must contain a nonempty scalar `id`. `sent` means the provider accepted the request; it does not mean the phone received it. HTTP responses also include `http_status`, `body`, `headers`, and `log_saved`. Avoid displaying or logging raw provider responses.

- `failed`: validation/local log creation failed before sending, or the provider returned a definite client rejection (4xx other than 408).
- `unknown`: timeout/connection error, 408, 5xx, redirect, or an ambiguous success response. The provider may have sent the message. **Check the provider before retrying.**
- `sent` with `log_saved=false`: the provider accepted the SMS, but saving its result failed. Preserve the returned ID for reconciliation; do not send again to repair logging.

One pending log is created before the single-send HTTP request. Logs record sender, recipient, message, optional model IDs, status, and provider message ID. Application logs contain only operational status, not message bodies, account keys, or callback headers. Message content and phone numbers remain personal data in the `sms_logs` database table: apply your application's permissions and retention policy.

There are no automatic HTTP retries, provider idempotency keys, or balance/account-verification methods in this package. Queue jobs must not retry an ambiguous send blindly. A process killed during a request can leave a pending log requiring reconciliation.

## Separate account configuration

Pass overrides to a fresh service instance when using multiple accounts or a long-lived worker:

```php
use Puresms\Laravel\PureSmsService;

$sms = new PureSmsService([
    'api_key' => $secureAccountKey,
    'endpoint' => 'https://connect-api.divergent.cloud',
    'sender' => 'YourSender',
    'number_model' => App\Models\Customer::class,
    'mobile_number' => 'mobile',
]);
$result = $sms->sendSms($approvedTestNumber, 'Integration test');
```

This does not mutate global configuration. The package has no sandbox or test-recipient mode: use `Http::fake()` for automated tests and enforce a test-number allowlist in the application before making any real request. A master account key can incur charges for every real send.

## Bulk SMS

`sendSmsBatch(array $messages, ?string $sendAtUtc = null)` posts `{messages: [...], sendAtUtc?: ...}` to `/sms/send/bulk`. Each message contains `sender` (optional), `recipient`, and `content`. It returns `status` (`success`, `failed`, `unknown`), `batch_id`, `message_count`, `body`, `headers`, and `log_saved` when an HTTP response is received.

The current database schema does not store batch IDs or per-message bulk IDs, so individual bulk delivery callbacks cannot be correlated reliably. Do not use bulk sending where per-recipient delivery tracking is required. Unknown results must be reconciled before retrying.

## Optional signed webhooks

Register a webhook only after confirming the provider's signing configuration. This package enforces the signing format already present in the original package; **it has not been verified against a live PureSMS callback**:

- `X-Webhook-Timestamp`: Unix timestamp in seconds.
- `X-Webhook-Signature`: base64 of binary HMAC-SHA256 over `timestamp + raw request body`, using the webhook secret.
- Timestamp must be within `webhook_tolerance` seconds of the server clock (default 300).

Set a separate `PURESMS_WEBHOOK_SECRET` and explicitly register an API route:

```php
use Illuminate\Support\Facades\Route;

Route::puresmsWebhooks('puresms-webhook')->middleware('throttle:60,1');
```

Use the actual URL produced by your application's API prefix. Missing/invalid signatures or stale timestamps return 403 before any payload processing. The legacy `services.puresms.webhook_secret` setting remains a fallback; never disable verification to work around a mismatched provider contract. Applications registering the route in `web.php` must deliberately configure the framework's CSRF handling for this signed machine endpoint.

Payloads use `data.MessageId`, `data.DeliveryStatus`, `data.ErrorCode`, `data.ProcessedAt`, and `data.DeliveredAt` (field case is ignored). Delivery codes `1`, `2`, and `7` map to `processing`, `failed`, and `delivered`; numeric strings are accepted. A delivered message cannot be regressed by an older callback.

Inbound payloads use `event_type=2` and `data.MessageId`, `InboundNumber`, `Sender`, `Body`, `ReceivedAt`. The configured number model/field locates the sender. Duplicate message IDs do not create additional logs. Database failures are not acknowledged as successful receipt.

## Testing and updating

The tests use mocked HTTP and in-memory SQLite; they do not bootstrap an application's environment or send messages. To run using an existing Laravel application's dependencies (PHPUnit 12 and Laravel 13 were tested):

```powershell
$env:PURESMS_TEST_AUTOLOAD = 'C:\path\to\app\vendor\autoload.php'
php C:\path\to\app\vendor\phpunit\phpunit\phpunit --bootstrap tests/bootstrap.php tests/PureSmsServiceTest.php
```

To update an installed release:

```sh
composer update mendelkahan/laravel-puresms
```

Review changes to published configuration and migrations before deployment. Version 1.1.4 adds no database migration; it changes HTTP failure results to explicit status strings and requires authenticated, fresh webhook signatures.
