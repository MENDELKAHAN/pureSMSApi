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

    public function __construct()
    {
        $this->apiKey = config('puresms.api_key');
        $this->endpoint = config('puresms.endpoint');
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
            // Log failure and message being sent
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

            // Optionally log to DB even on failure
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
        // Log exception with payload details
        Log::error('PureSMS API exception thrown', [
            'error'        => $e->getMessage(),
            'to'           => $to,
            'from'         => $from,
            'message'      => $message,
            'recipient_id' => $recipientId,
            'sender_id'    => $senderId,
        ]);

        // Optionally log to DB on exception
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
        // Ensure messages array is properly structured
        $payload = [
            'messages' => array_map(function ($message) {
                return [
                    'sender' => $message['sender'] ?? env('PURESMS_SENDER', 'ConnectTest'),
                    'recipient' => $message['recipient'],
                    'content' => $message['content']
                ];
            }, $messages)
        ];

        // Include scheduled time if provided
        if ($sendAtUtc) {
            $payload['sendAtUtc'] = $sendAtUtc;
        }

        // Convert payload to JSON
        $jsonPayload = json_encode($payload);

        try {
            // Make API request
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Content-Length' => strlen($jsonPayload),
                'X-API-Key' => $this->apiKey,
            ])->post("{$this->endpoint}/sms/send/bulk", $payload);

            // Get response data
            $responseData = $response->json();

            // Log batch messages in the database
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

            // Log API response
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
            // Log errors
            Log::error('PureSMS Bulk API Error:', ['message' => $e->getMessage()]);

            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }



    public function handleWebhook(Request $request)
    {

        Log::info('Webhook received', [
            'headers' => $request->headers->all(),
            'body'    => $request->all(),
        ]);

        // 1. (Optional) Validate the webhook signature if the provider sends an X-Webhook-Signature or X-Webhook-Timestamp
        // $this->validateWebhookSignature($request);

        // 2. Check if this payload looks like an inbound message
        //    (i.e. it has 'messageId', 'inboundNumber', 'sender', 'body', 'receivedAt')
        if (
            $request->has('messageId') &&
            $request->has('inboundNumber') &&
            $request->has('sender') &&
            $request->has('body') &&
            $request->has('receivedAt')
        ) {
            return $this->handleInboundSms($request);
        }
        
        // 3. Otherwise, fall back to your existing delivery-status logic
        //    (the “status update” that uses 'data.MessageId' etc).
        $data = $request->input('data');
        $data = $request->input('data');

        if (empty($data)) {
            Log::error('Webhook: no data or unrecognized payload');
            return response()->json(['message' => 'Webhook processed, but no recognized content'], 200);
        }

        // 🔽 Normalize keys to lowercase
        $data = array_change_key_case($data, CASE_LOWER);

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

    /**
     * Example method to handle the inbound SMS scenario.
     */
    /**
     * Example method to handle the inbound SMS scenario.
     * In this version, we store the inbound data
     * into the existing "sms_logs" table via SmsLog model.
     */
    protected function handleInboundSms(Request $request)
    {

        


        $messageId     = $request->input('messageId');
        $inboundNumber = $request->input('inboundNumber'); // The number on *your* side (the "recipient" in your system)
        $sender        = $request->input('sender');        // The phone number that sent the SMS
        $body          = $request->input('body');
        $receivedAt    = $request->input('receivedAt');

        try {
        $dt = new \DateTime($receivedAt);
    // MySQL DATETIME minimum valid value is '1000-01-01 00:00:00'
        if ($dt->format('Y') < 1000) {
            $receivedAtFormatted = null;
        } else {
            $receivedAtFormatted = $dt->format('Y-m-d H:i:s');
        }
        } catch (\Exception $e) {
            $receivedAtFormatted = null; // fallback if there's a parsing error
            Log::error('Invalid receivedAt timestamp: ' . $receivedAt);
        }

        Log::info('Inbound SMS Webhook:', [
            'messageId'     => $messageId,
            'inboundNumber' => $inboundNumber,
            'sender'        => $sender,
            'body'          => $body,
            'receivedAt'    => $receivedAtFormatted,
        ]);

        if ($user = \App\Models\User::where('sms_number', $sender)->first()) {
            $sender_id = $user->id;
           
        }else{
              $sender_id = null;
        }

        // Store in the same "sms_logs" table
        $smsLog = SmsLog::create([
            'message_id'   => $messageId,
            // "recipient" is the inbound number on your side
            'recipient'    => $inboundNumber, 
            // "sender" is the phone who sent the SMS
            'sender'       => $sender,       
            // The SMS body goes into "content"
            'content'      => $body,         
            // You can mark inbound messages with a custom status
            'status'       => 'received',    
            'processed_at' => $receivedAtFormatted,
            // For inbound, you may not need delivered_at, 
            // but you could set it if you want:
            'delivered_at' => $receivedAtFormatted,
            // If you’re not using error_code for inbound,
            // you can safely leave it null or omit it.
            'error_code'   => null,
            'sender_id' => $sender_id ,
        ]);

     
       
        
        

        return response()->json(['message' => 'Inbound SMS processed'], 200);
    }


    /**
     * (Optional) Example of verifying the webhook signature if your provider includes it.
     * Adjust the code for your provider's exact signing algorithm.
     */
    protected function validateWebhookSignature(Request $request)
    {
        $signature   = $request->header('X-Webhook-Signature');
        $timestamp   = $request->header('X-Webhook-Timestamp');
        $secretKey   = config('services.puresms.webhook_secret'); // for example
        $body        = $request->getContent();

        // Possibly your provider wants something like
        // HMAC-SHA256(timestamp + body, secretKey) == $signature
        $computedHmac = base64_encode(
            hash_hmac('sha256', $timestamp . $body, $secretKey, true)
        );

        if (!hash_equals($computedHmac, $signature)) {
            Log::error('Invalid webhook signature');
            abort(403, 'Invalid webhook signature');
        }
    }




    /**
     * Map PureSMS Delivery Status codes to readable statuses.
     */
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