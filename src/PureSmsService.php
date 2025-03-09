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
    public function sendSms($to, $message, $from = null)
    {
        $payload = [
            'sender' => $from ?? env('PURESMS_SENDER', 'ConnectTest'),
            'recipient' => $to,
            'content' => $message
        ];

        // Convert array to JSON
        $jsonPayload = json_encode($payload);

        try {
            // Make API request using Laravel's Http facade
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Content-Length' => strlen($jsonPayload),
                'X-API-Key' => $this->apiKey,
            ])->post("{$this->endpoint}/sms/send", $payload);
            
            

            // this is to see what i aget from the api 
            
            if(is_null($response)){
                Log::error('PureSMS API Error: No response from Puresms');
                return false;
            }


            // Get response data
            $responseData = $response->json();

            // Determine status
            $status = $response->successful() && isset($responseData['id']) ? 'sent' : 'failed';
            $messageId = $responseData['id'] ?? null;
            $errorCode = $response->successful() ? null : $response->status();

            // Log SMS in the database
            SmsLog::create([
                'message_id' => $messageId,
                'recipient' => $to,
                'sender' => $payload['sender'],
                'content' => $message,
                'status' => $status,
                'error_code' => $errorCode,
                'processed_at' => now(),
            ]);

            // Log API response for debugging
            Log::info('PureSMS API Response:', [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers()
            ]);

            return [
                'status' => $status,
                'message_id' => $messageId,
                'body' => $responseData,
                'headers' => $response->headers()
            ];
        } catch (\Exception $e) {
            // Log errors
            Log::error('PureSMS API Error:', ['message' => $e->getMessage()]);

            return [
                'error' => true,
                'message' => $e->getMessage()
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




    /**
     * Handle incoming webhook from PureSMS.
     */
   

public function handleWebhook(Request $request)
{
    Log::error($request);
    $data = $request->input('data');

    Log::info('PureSMS Webhook:', [
        'MessageId'   => $data['MessageId'] ?? null,
        'Status'      => $data['DeliveryStatus'] ?? null,
        'ErrorCode'   => $data['ErrorCode'] ?? null,
        'ProcessedAt' => $data['ProcessedAt'] ?? null,
        'DeliveredAt' => $data['DeliveredAt'] ?? null,
    ]);

    // Convert ISO8601 timestamp using PHP's DateTime class
    $processedAt = isset($data['ProcessedAt'])
        ? (new \DateTime($data['ProcessedAt']))->format('Y-m-d H:i:s')
        : null;
    $deliveredAt = isset($data['DeliveredAt'])
        ? (new \DateTime($data['DeliveredAt']))->format('Y-m-d H:i:s')
        : null;

    // Update SMS status in the database
    SmsLog::where('message_id', $data['MessageId'])
        ->update([
            'status'       => $this->mapDeliveryStatus($data['DeliveryStatus']),
            'error_code'   => $data['ErrorCode'] ?? null,
            'processed_at' => $processedAt,
            'delivered_at' => $deliveredAt,
        ]);

    return response()->json(['message' => 'Webhook processed'], 200);
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
