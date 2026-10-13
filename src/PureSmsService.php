<?php

namespace Puresms\Laravel;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Puresms\Laravel\Models\SmsLog;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PureSmsService
{
    protected array $options;

    public function __construct(array $options = [])
    {
        $this->options = array_replace((array) config('puresms', []), $options);
    }

    /**
     * Send once. An unknown result must be reconciled before any retry.
     */
    public function sendSms($to, $message, $from = null, $recipientId = null, $senderId = null)
    {
        $payload = [
            'sender' => $from ?: ($this->options['sender'] ?? 'ConnectTest'),
            'recipient' => $to,
            'content' => $message,
        ];

        if (! $this->validConfiguration() || ! $this->validMessage($payload)) {
            return $this->failure('failed', 'Invalid SMS configuration or message.', 'invalid_configuration');
        }

        $model = $this->options['model'] ?? SmsLog::class;
        try {
            // Persist before sending, so a failed database write never sends an untracked SMS.
            $log = $model::create([
                'recipient' => $to,
                'sender' => $payload['sender'],
                'recipient_id' => $recipientId,
                'sender_id' => $senderId,
                'content' => $message,
                'status' => 'pending',
            ]);
        } catch (\Throwable $e) {
            Log::error('PureSMS could not create a send log');

            return $this->failure('failed', 'SMS was not sent because its log could not be saved.', 'log_unavailable');
        }

        try {
            $response = $this->http()->post($this->endpoint().'/sms/send', $payload);
        } catch (\Throwable $e) {
            $saved = $this->updateLog($log, ['status' => 'unknown', 'error_code' => 'transport_error', 'processed_at' => now()]);

            return $this->failure('unknown', 'The SMS result is unknown. Check with the provider before retrying.', 'transport_error')
                + ['log_saved' => $saved];
        }

        $body = $response->json();
        $id = is_array($body) ? ($body['id'] ?? null) : null;
        $validId = (is_string($id) || is_int($id)) && trim((string) $id) !== '';
        $status = $response->successful() && $validId ? 'sent'
            : ($response->clientError() && $response->status() !== 408 ? 'failed' : 'unknown');
        $messageId = $status === 'sent' ? (string) $id : null;
        $saved = $this->updateLog($log, [
            'message_id' => $messageId,
            'status' => $status,
            'error_code' => $status === 'sent' ? null : (string) $response->status(),
            'processed_at' => now(),
        ]);

        Log::info('PureSMS send result', ['status' => $status, 'http_status' => $response->status(), 'log_saved' => $saved]);

        return [
            'error' => $status !== 'sent',
            'status' => $status,
            'message_id' => $messageId,
            'http_status' => $response->status(),
            'body' => $body,
            'headers' => $response->headers(),
            'log_saved' => $saved,
            'message' => match ($status) {
                'sent' => 'SMS accepted by the provider.',
                'failed' => 'SMS rejected by the provider.',
                default => 'The SMS result is unknown. Check with the provider before retrying.',
            },
        ];
    }

    public function sendSmsBatch(array $messages, ?string $sendAtUtc = null)
    {
        $messages = array_map(fn ($message) => [
            'sender' => $message['sender'] ?? ($this->options['sender'] ?? 'ConnectTest'),
            'recipient' => $message['recipient'] ?? null,
            'content' => $message['content'] ?? null,
        ], $messages);
        if (! $this->validConfiguration() || $messages === [] || array_filter($messages, fn ($message) => ! $this->validMessage($message))) {
            return $this->failure('failed', 'Invalid SMS configuration or message.', 'invalid_configuration');
        }

        $payload = ['messages' => $messages];
        if ($sendAtUtc) {
            $payload['sendAtUtc'] = $sendAtUtc;
        }

        try {
            $response = $this->http()->post($this->endpoint().'/sms/send/bulk', $payload);
        } catch (\Throwable $e) {
            return $this->failure('unknown', 'The bulk SMS result is unknown. Check with the provider before retrying.', 'transport_error');
        }

        $body = $response->json();
        $batchId = is_array($body) ? ($body['batchId'] ?? null) : null;
        $validId = (is_string($batchId) || is_int($batchId)) && trim((string) $batchId) !== '';
        $status = $response->successful() && $validId ? 'success'
            : ($response->clientError() && $response->status() !== 408 ? 'failed' : 'unknown');
        $saved = true;
        $model = $this->options['model'] ?? SmsLog::class;
        foreach ($messages as $message) {
            try {
                $model::create($message + [
                    'status' => $status === 'success' ? 'sent' : $status,
                    'error_code' => $status === 'success' ? null : (string) $response->status(),
                    'processed_at' => now(),
                ]);
            } catch (\Throwable $e) {
                $saved = false;
                Log::error('PureSMS could not save a bulk send log');
            }
        }

        return [
            'error' => $status !== 'success',
            'status' => $status,
            'batch_id' => $status === 'success' ? (string) $batchId : null,
            'message_count' => is_array($body) ? ($body['messageCount'] ?? null) : null,
            'body' => $body,
            'headers' => $response->headers(),
            'log_saved' => $saved,
        ];
    }

    public function handleWebhook(Request $request)
    {
        $this->validateWebhookSignature($request);
        $data = $request->input('data');
        if (! is_array($data)) {
            throw new HttpException(422, 'Invalid webhook data.');
        }

        $data = array_change_key_case($data, CASE_LOWER);
        if (! isset($data['messageid']) || ! is_scalar($data['messageid']) || trim((string) $data['messageid']) === '') {
            throw new HttpException(422, 'Missing webhook message ID.');
        }

        if ((int) $request->input('event_type') === 2) {
            return $this->handleInboundSms($data);
        }

        $status = $this->mapDeliveryStatus($data['deliverystatus'] ?? null);
        if ($status === 'unknown') {
            throw new HttpException(422, 'Unsupported delivery status.');
        }

        $model = $this->options['model'] ?? SmsLog::class;
        // Delivery is terminal: delayed callbacks must not regress a delivered message.
        $model::where('message_id', (string) $data['messageid'])
            ->where('status', '!=', 'delivered')
            ->update([
                'status' => $status,
                'error_code' => $data['errorcode'] ?? null,
                'processed_at' => $this->timestamp($data['processedat'] ?? null),
                'delivered_at' => $this->timestamp($data['deliveredat'] ?? null),
            ]);

        return new JsonResponse(['message' => 'Delivery status processed']);
    }

    protected function handleInboundSms(array $data)
    {
        foreach (['inboundnumber', 'sender', 'body'] as $field) {
            if (! isset($data[$field]) || ! is_string($data[$field])) {
                throw new HttpException(422, 'Invalid inbound SMS data.');
            }
        }

        $numberModel = $this->options['number_model'] ?? User::class;
        $senderId = $numberModel::where($this->options['mobile_number'] ?? 'sms_number', $data['sender'])->value('id');
        $receivedAt = $this->timestamp($data['receivedat'] ?? null);
        $model = $this->options['model'] ?? SmsLog::class;
        $model::firstOrCreate(['message_id' => (string) $data['messageid']], [
            'recipient' => $data['inboundnumber'],
            'sender' => $data['sender'],
            'content' => $data['body'],
            'status' => 'received',
            'processed_at' => $receivedAt,
            'delivered_at' => $receivedAt,
            'sender_id' => $senderId,
        ]);

        return new JsonResponse(['message' => 'Inbound SMS processed']);
    }

    protected function validateWebhookSignature(Request $request)
    {
        $secret = $this->options['webhook_secret'] ?? config('services.puresms.webhook_secret');
        $signature = $request->header('X-Webhook-Signature');
        $timestamp = $request->header('X-Webhook-Timestamp');
        $tolerance = max(1, (int) ($this->options['webhook_tolerance'] ?? 300));

        if (! is_string($secret) || trim($secret) === '' || ! is_string($signature) || ! is_string($timestamp)
            || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > $tolerance) {
            throw new HttpException(403, 'Invalid webhook signature.');
        }

        $expected = base64_encode(hash_hmac('sha256', $timestamp.$request->getContent(), $secret, true));
        if (! hash_equals($expected, $signature)) {
            throw new HttpException(403, 'Invalid webhook signature.');
        }
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->asJson()->withHeaders(['X-API-Key' => $this->options['api_key']])
            ->timeout(max(1, (int) ($this->options['timeout'] ?? 20)))
            ->connectTimeout(max(1, (int) ($this->options['connect_timeout'] ?? 5)))
            ->withOptions(['allow_redirects' => false]);
    }

    private function endpoint(): string
    {
        return rtrim((string) ($this->options['endpoint'] ?? ''), '/');
    }

    private function validConfiguration(): bool
    {
        $endpoint = parse_url($this->endpoint());

        return is_string($this->options['api_key'] ?? null) && trim($this->options['api_key']) !== ''
            && is_array($endpoint) && ($endpoint['scheme'] ?? null) === 'https' && ! empty($endpoint['host'])
            && ! isset($endpoint['user']) && ! isset($endpoint['pass']) && ! isset($endpoint['query']) && ! isset($endpoint['fragment']);
    }

    private function validMessage(array $payload): bool
    {
        foreach (['sender', 'recipient', 'content'] as $field) {
            if (! is_string($payload[$field]) || trim($payload[$field]) === '') {
                return false;
            }
        }

        return true;
    }

    private function updateLog($log, array $attributes): bool
    {
        try {
            $log->update($attributes);

            return true;
        } catch (\Throwable $e) {
            Log::error('PureSMS could not update a send log', ['status' => $attributes['status']]);

            return false;
        }
    }

    private function failure(string $status, string $message, string $code): array
    {
        return ['error' => true, 'status' => $status, 'message' => $message, 'error_code' => $code, 'message_id' => null];
    }

    private function timestamp($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->utc()->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            throw new HttpException(422, 'Invalid webhook timestamp.');
        }
    }

    private function mapDeliveryStatus($status): string
    {
        return match ((string) $status) {
            '1' => 'processing',
            '2' => 'failed',
            '7' => 'delivered',
            default => 'unknown',
        };
    }
}
