<?php

namespace Puresms\Laravel;

use Puresms\Laravel\Models\SmsLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
    public function sendSms(string $to, string $message, string $sender = 'PureSMS')
    {
        // Save the SMS to the database
        $sms = SmsLog::create([
            'recipient' => $to,
            'sender' => $sender,
            'content' => $message,
            'status' => 'pending',
        ]);

        // Send the SMS via API
        $response = Http::withHeaders([
            'X-Api-Key' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->post($this->endpoint . '/sms/send', [
            'sender' => $sender,
            'recipient' => $to,
            'content' => $message
        ]);

        $responseData = $response->json();

        // Update the database with the Message ID
        if ($response->successful() && isset($responseData['id'])) {
            $sms->update([
                'message_id' => $responseData['id'],
                'status' => 'sent'
            ]);
        } else {
            $sms->update([
                'status' => 'failed',
                'error_code' => $response->status()
            ]);
        }

        return $responseData;
    }

    /**
     * Handle incoming webhook from PureSMS.
     */
    public function handleWebhook(Request $request)
    {
        $data = $request->input('data');

        Log::info('PureSMS Webhook:', [
            'MessageId' => $data['MessageId'] ?? null,
            'Status' => $data['DeliveryStatus'] ?? null,
            'ErrorCode' => $data['ErrorCode'] ?? null,
            'ProcessedAt' => $data['ProcessedAt'] ?? null,
            'DeliveredAt' => $data['DeliveredAt'] ?? null
        ]);

        // Update SMS status in the database
        SmsLog::where('message_id', $data['MessageId'])
            ->update([
                'status' => $this->mapDeliveryStatus($data['DeliveryStatus']),
                'error_code' => $data['ErrorCode'] ?? null,
                'processed_at' => $data['ProcessedAt'],
                'delivered_at' => $data['DeliveredAt'] ?? null
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
