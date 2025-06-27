<?php

namespace Puresms\Laravel;

use Puresms\Laravel\Models\SmsLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PureSmsService
{
    protected $apiKey;
    protected $endpoint;
    protected $numberModel;
    protected $mobileNumber;

    public function __construct()
    {
        $this->apiKey = config('puresms.api_key');
        $this->endpoint = config('puresms.endpoint');
        $this->numberModel = config('puresms.number_model', \App\Models\User::class);
        $this->mobileNumber = config('puresms.mobile_number', 'sms_number');
    }

    /**
     * Send an SMS and store it in the database.
     */
    public function sendSms($to, $message, $from = null, $recipientId = null, $senderId = null)
    {
        $payload = [
            'sender'    => $from ?? env('PURESMS_SENDER', 'ConnectTest'),
            'recipient' => $to,
            'content'   => $message,
        ];

        $jsonPayload = json_encode($payload);

        try {
            $response = Http::withHeaders([
                'Content-Type'   => 'application/json',
                'Content-Length' => strlen($jsonPayload),
                'X-API-Key'      => $this->apiKey,
            ])->post("{$this->endpoint}/sms/send", $payload);

            if ($response->failed()) {
                Log::error('PureSMS API request failed', [
                    'status'     => $response->status(),
                    'body'       => $response->body(),
                    'headers'    => $response->headers(),
                    'to'         => $to,
                    'from'       => $from,
                    'message'    => $message,
                    'recipient_id' => $recipientId,
                    'sender_id'    => $senderId,
                ]);

                SmsLog::create([
                    'message_id'   => null,
                    'recipient_id' => $recipientId,
                    'sender_id'    => $senderId,
                    'content'      => $message,
                    'status'       => 'failed',
                    'error_code'   => $response->status(),
                    'processed_at' => now(),
                ]);

                return [
                    'error'   => true,
                    'message' => 'API call failed',
                    'status'  => $response->status(),
                ];
            }

            $responseData = $response->json();
            $status = isset($responseData['id']) ? 'sent' : 'failed';
            $messageId = $responseData['id'] ?? null;

            SmsLog::create([
                'message_id'   => $messageId,
                'recipient_id' => $recipientId,
                'sender_id'    => $senderId,
                'content'      => $message,
                'status'       => $status,
                'error_code'   => $status === 'sent' ? null : $response->status(),
                'processed_at' => now(),
            ]);

            Log::info('PureSMS API successful response', [
                'status'  => $response->status(),
                'body'    => $response->body(),
                'headers' => $response->headers(),
            ]);

            return [
                'status'     => $status,
                'message_id' => $messageId,
                'body'       => $responseData,
                'headers'    => $response->headers(),
            ];
        } catch (\Exception $e) {
            Log::error('PureSMS API exception thrown', [
                'error'        => $e->getMessage(),
                'to'           => $to,
                'from'         => $from,
                'message'      => $message,
                'recipient_id' => $recipientId,
                'sender_id'    => $senderId,
            ]);

            SmsLog::create([
                'message_id'   => null,
                'recipient_id' => $recipientId,
                'sender_id'    => $senderId,
                'content'      => $message,
                'status'       => 'failed',
                'error_code'   => 'exception',
                'processed_at' => now(),
            ]);

            return [
                'error'   => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function sendSmsBatch(array $messages, ?string $sendAtUtc = null)
    {
        $payload = [
            'messages' => array_map(function ($message) {
                return [
                    'sender' => $message['sender'] ?? env('PURESMS_SENDER', 'ConnectTest'),
                    'recipient' => $message['recipient'],
                    'content' => $message['content']
                ];
            }, $messages)
        ];

        if ($sendAtUtc) {
            $payload['sendAtUtc'] = $sendAtUtc;
        }

        $jsonPayload = json_encode($payload);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Content-Length' => strlen($jsonPayload),
                'X-API-Key' => $this->apiKey,
            ])->post("{$this->endpoint}/sms/send/bulk", $payload);

            $responseData = $response->json();

            foreach ($messages as $message) {
                SmsLog::create([
                    'batch_id' => $responseData['batchId'] ?? null,
                    'recipient' => $message['recipient'],
                    'sender' => $message['sender'] ?? env('PURESMS_SENDER', 'ConnectTest'),
                    'content' => $message['content'],
                    'status' => $response->successful() ? 'sent' : 'failed',
                    'error_code' => $response->successful() ? null : $response->status(),
                    'processed_at' => now(),
                ]);
            }

            Log::info('PureSMS Bulk API Response:', [
                'status' => $response->status(),
                'batch_id' => $responseData['batchId'] ?? null,
                'message_count' => $responseData['messageCount'] ?? null,
                'body' => $response->body(),
                'headers' => $response->headers()
            ]);

            return [
                'status' => $response->successful() ? 'success' : 'failed',
                'batch_id' => $responseData['batchId'] ?? null,
                'message_count' => $responseData['messageCount'] ?? null,
                'body' => $responseData,
                'headers' => $response->headers()
            ];
        } catch (\Exception $e) {
            Log::error('PureSMS Bulk API Error:', ['message' => $e->getMessage()]);

            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    public function handleWebhook(Request $request)
    {
        $payload = $request->all();

        if ($request->event_type === 2) {
            return $this->handleInboundSms($request);
        }

        $data = $request->input('data');
        $data = array_change_key_case($data, CASE_LOWER);

        if (empty($data)) {
            Log::error('Webhook: no data or unrecognized payload');
            return response()->json(['message' => 'Webhook processed, but no recognized content'], 200);
        }

        Log::info('PureSMS Webhook:', [
            'MessageId'   => $data['messageid'] ?? null,
            'Status'      => $data['deliverystatus'] ?? null,
            'ErrorCode'   => $data['errorcode'] ?? null,
            'ProcessedAt' => $data['processedat'] ?? null,
            'DeliveredAt' => $data['deliveredat'] ?? null,
        ]);

        $processedAt = isset($data['processedat'])
            ? (new \DateTime($data['processedat']))->format('Y-m-d H:i:s')
            : null;

        $deliveredAt = isset($data['deliveredat'])
            ? (new \DateTime($data['deliveredat']))->format('Y-m-d H:i:s')
            : null;

        SmsLog::where('message_id', $data['messageid'] ?? null)
            ->update([
                'status'       => $this->mapDeliveryStatus($data['deliverystatus'] ?? null),
                'error_code'   => $data['errorcode'] ?? null,
                'processed_at' => $processedAt,
                'delivered_at' => $deliveredAt,
            ]);

        return response()->json(['message' => 'Delivery status processed'], 200);
    }

    protected function handleInboundSms(Request $request)
    {
        $data = array_change_key_case($request->data, CASE_LOWER);

        $messageId     = $data['messageid'];
        $inboundNumber = $data['inboundnumber'];
        $sender        = $data['sender'];
        $body          = $data['body'];
        $receivedAt    = $data['receivedat'];

        try {
            $dt = new \DateTime($receivedAt);
            if ($dt->format('Y') < 1000) {
                $receivedAtFormatted = null;
            } else {
                $receivedAtFormatted = $dt->format('Y-m-d H:i:s');
            }
        } catch (\Exception $e) {
            $receivedAtFormatted = null;
            Log::error('Invalid receivedAt timestamp: ' . $receivedAt);
        }

        Log::info('Inbound SMS Webhook:', [
            'messageId'     => $messageId,
            'inboundNumber' => $inboundNumber,
            'sender'        => $sender,
            'body'          => $body,
            'receivedAt'    => $receivedAtFormatted,
        ]);

        $modelClass = $this->numberModel;
        $sender_id = ($modelClass::where($this->mobileNumber, $sender)->first())?->id;

        try {
            $smsLog = SmsLog::create([
                'message_id'   => $messageId,
                'recipient'    => $inboundNumber,
                'sender'       => $sender,
                'content'      => $body,
                'status'       => 'received',
                'processed_at' => $receivedAtFormatted,
                'delivered_at' => $receivedAtFormatted,
                'error_code'   => null,
                'sender_id'    => $sender_id,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::warning('Duplicate SMS log prevented:', [
                'error'      => $e->getMessage(),
                'message_id' => $messageId,
            ]);

            return response()->json(['message' => 'Duplicate entry ignored'], 200);
        }

        return response()->json(['message' => 'Inbound SMS processed'], 200);
    }

    protected function validateWebhookSignature(Request $request)
    {
        $signature   = $request->header('X-Webhook-Signature');
        $timestamp   = $request->header('X-Webhook-Timestamp');
        $secretKey   = config('services.puresms.webhook_secret');
        $body        = $request->getContent();

        $computedHmac = base64_encode(
            hash_hmac('sha256', $timestamp . $body, $secretKey, true)
        );

        if (!hash_equals($computedHmac, $signature)) {
            Log::error('Invalid webhook signature');
            abort(403, 'Invalid webhook signature');
        }
    }

    private function mapDeliveryStatus($status)
    {
        return match ($status) {
            1 => 'processing',
            2 => 'failed',
            7 => 'delivered',
            default => 'unknown'
        };
    }
}