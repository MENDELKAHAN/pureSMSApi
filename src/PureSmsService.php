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
<<<<<<< HEAD
    public function sendSms($to, $message, $from = null, $recipientId = null, $senderId = null)
    {
        $payload = [
            'sender'    => $from ?? env('PURESMS_SENDER', 'ConnectTest'),
            'recipient' => $to,
            'content'   => $message,
        ];
=======
public function sendSms($to, $message, $from = null, $recipientId = null, $senderId = null)
{
    $payload = [
        'sender'    => $from ?? env('PURESMS_SENDER', 'ConnectTest'),
        'recipient' => $to,
        'content'   => $message,
    ];
>>>>>>> 3345b05349e16a1c139edd63155e3c87bdfc7448

    // Convert array to JSON
    $jsonPayload = json_encode($payload);

<<<<<<< HEAD
        try {
            // Make API request using Laravel's Http facade
            $response = Http::withHeaders([
                'Content-Type'   => 'application/json',
                'Content-Length' => strlen($jsonPayload),
                'X-API-Key'      => $this->apiKey,
            ])->post("{$this->endpoint}/sms/send", $payload);

            if (is_null($response)) {
                Log::error('PureSMS API Error: No response from Puresms');
                return false;
            }

            // Get response data
            $responseData = $response->json();

            // Determine status
            $status = $response->successful() && isset($responseData['id']) ? 'sent' : 'failed';
            $messageId = $responseData['id'] ?? null;
            $errorCode = $response->successful() ? null : $response->status();

            // Log SMS in the database using recipient_id and sender_id
            SmsLog::create([
                'message_id'   => $messageId,
                'recipient_id' => $recipientId,
                'sender_id'    => $senderId,
                'content'      => $message,
                'status'       => $status,
                'error_code'   => $errorCode,
                'processed_at' => now(),
            ]);

            // Log API response for debugging
            Log::info('PureSMS API Response:', [
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
            // Log errors
            Log::error('PureSMS API Error:', ['message' => $e->getMessage()]);

            return [
                'error'   => true,
                'message' => $e->getMessage(),
            ];
=======
    try {
        // Make API request using Laravel's Http facade
        $response = Http::withHeaders([
            'Content-Type'   => 'application/json',
            'Content-Length' => strlen($jsonPayload),
            'X-API-Key'      => $this->apiKey,
        ])->post("{$this->endpoint}/sms/send", $payload);

        if (is_null($response)) {
            Log::error('PureSMS API Error: No response from Puresms');
            return false;
>>>>>>> 3345b05349e16a1c139edd63155e3c87bdfc7448
        }

        // Get response data
        $responseData = $response->json();

        // Determine status
        $status = $response->successful() && isset($responseData['id']) ? 'sent' : 'failed';
        $messageId = $responseData['id'] ?? null;
        $errorCode = $response->successful() ? null : $response->status();

        // Log SMS in the database using recipient_id and sender_id
        SmsLog::create([
            'message_id'   => $messageId,
            'recipient_id' => $recipientId,
            'sender_id'    => $senderId,
            'content'      => $message,
            'status'       => $status,
            'error_code'   => $errorCode,
            'processed_at' => now(),
        ]);

        // Log API response for debugging
        Log::info('PureSMS API Response:', [
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
        // Log errors
        Log::error('PureSMS API Error:', ['message' => $e->getMessage()]);

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



<<<<<<< HEAD
    public function handleWebhook(Request $request)
    {


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

        if (empty($data)) {
            // No data for a delivery report nor inbound SMS
            Log::error('Webhook: no data or unrecognized payload');
            return response()->json(['message' => 'Webhook processed, but no recognized content'], 200);
        }

        // If we get here, we assume it’s a status update. Proceed as you do now:
        Log::info('PureSMS Webhook:', [
            'MessageId'   => $data['MessageId'] ?? null,
            'Status'      => $data['DeliveryStatus'] ?? null,
            'ErrorCode'   => $data['ErrorCode'] ?? null,
            'ProcessedAt' => $data['ProcessedAt'] ?? null,
            'DeliveredAt' => $data['DeliveredAt'] ?? null,
        ]);

        // Convert ISO8601 timestamps
        $processedAt = isset($data['ProcessedAt'])
            ? (new \DateTime($data['ProcessedAt']))->format('Y-m-d H:i:s')
            : null;
        $deliveredAt = isset($data['DeliveredAt'])
            ? (new \DateTime($data['DeliveredAt']))->format('Y-m-d H:i:s')
            : null;

        // Update delivery status in your DB
        SmsLog::where('message_id', $data['MessageId'])
            ->update([
                'status'       => $this->mapDeliveryStatus($data['DeliveryStatus']),
                'error_code'   => $data['ErrorCode'] ?? null,
                'processed_at' => $processedAt,
                'delivered_at' => $deliveredAt,
            ]);

        return response()->json(['message' => 'Delivery status processed'], 200);
=======

    /**
     * Handle incoming webhook from PureSMS.
     */
   

// public function handleWebhook(Request $request)
// {
  
//     if (empty($request->all())) { 
//         Log::error('no data');
//         return response()->json(['message' => 'Webhook processed'], 200);
//     }else{
//         $data = $request->input('data');

//         if (!empty($data)) {

//             Log::info('PureSMS Webhook:', [
//                 'MessageId'   => $data['MessageId'] ?? null,
//                 'Status'      => $data['DeliveryStatus'] ?? null,
//                 'ErrorCode'   => $data['ErrorCode'] ?? null,
//                 'ProcessedAt' => $data['ProcessedAt'] ?? null,
//                 'DeliveredAt' => $data['DeliveredAt'] ?? null,
//             ]);

//             // Convert ISO8601 timestamp using PHP's DateTime class
//             $processedAt = isset($data['ProcessedAt'])
//                 ? (new \DateTime($data['ProcessedAt']))->format('Y-m-d H:i:s')
//                 : null;
//             $deliveredAt = isset($data['DeliveredAt'])
//                 ? (new \DateTime($data['DeliveredAt']))->format('Y-m-d H:i:s')
//                 : null;


//             // Update SMS status in the database
//             SmsLog::where('message_id', $data['MessageId'])
//                 ->update([
//                     'status'       => $this->mapDeliveryStatus($data['DeliveryStatus']),
//                     'error_code'   => $data['ErrorCode'] ?? null,
//                     'processed_at' => $processedAt,
//                     'delivered_at' => $deliveredAt,
//                 ]);

           

//         }else{

//         Log::error('Data was empty');
//         return response()->json(['message' => 'Webhook processed'], 200);

//         }




//     }


//      return response()->json(['message' => 'Webhook processed'], 200);
// }




public function handleWebhook(Request $request)
{
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

    if (empty($data)) {
        // No data for a delivery report nor inbound SMS
        Log::error('Webhook: no data or unrecognized payload');
        return response()->json(['message' => 'Webhook processed, but no recognized content'], 200);
    }

    // If we get here, we assume it’s a status update. Proceed as you do now:
    Log::info('PureSMS Webhook:', [
        'MessageId'   => $data['MessageId'] ?? null,
        'Status'      => $data['DeliveryStatus'] ?? null,
        'ErrorCode'   => $data['ErrorCode'] ?? null,
        'ProcessedAt' => $data['ProcessedAt'] ?? null,
        'DeliveredAt' => $data['DeliveredAt'] ?? null,
    ]);

    // Convert ISO8601 timestamps
    $processedAt = isset($data['ProcessedAt'])
        ? (new \DateTime($data['ProcessedAt']))->format('Y-m-d H:i:s')
        : null;
    $deliveredAt = isset($data['DeliveredAt'])
        ? (new \DateTime($data['DeliveredAt']))->format('Y-m-d H:i:s')
        : null;

    // Update delivery status in your DB
    SmsLog::where('message_id', $data['MessageId'])
        ->update([
            'status'       => $this->mapDeliveryStatus($data['DeliveryStatus']),
            'error_code'   => $data['ErrorCode'] ?? null,
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
        // Convert ISO8601 timestamp to something your DB can handle
        $receivedAtFormatted = (new \DateTime($receivedAt))->format('Y-m-d H:i:s');
    } catch (\Exception $e) {
        $receivedAtFormatted = null; // fallback if there's a parsing error
        Log::error('Invalid receivedAt timestamp: ' . $receivedAt);
>>>>>>> 3345b05349e16a1c139edd63155e3c87bdfc7448
    }

    Log::info('Inbound SMS Webhook:', [
        'messageId'     => $messageId,
        'inboundNumber' => $inboundNumber,
        'sender'        => $sender,
        'body'          => $body,
        'receivedAt'    => $receivedAtFormatted,
    ]);

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
    ]);

    // (Optional) If you want to look up which user corresponds to the `sender` phone,
    // you can do so here and fill in `sender_id` or `recipient_id`.
    // For example:
    /*
    if ($user = \App\Models\User::where('phone_number', $sender)->first()) {
        $smsLog->sender_id = $user->id;
        $smsLog->save();
    }
    */

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
        ]);

        // (Optional) If you want to look up which user corresponds to the `sender` phone,
        // you can do so here and fill in `sender_id` or `recipient_id`.
        // For example:
       
        if ($user = \App\Models\User::where('sms_number', $sender)->first()) {
            $smsLog->sender_id = $user->id;
            $smsLog->save();
        }
        

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
