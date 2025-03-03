<?php

namespace Puresms\Laravel;

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
     * Send an SMS
     */
    public function sendSms(string $to, string $message, string $sender = 'PureSMS')
    {
        $response = Http::withHeaders([
            'X-Api-Key' => $this->apiKey,
            'Content-Type' => 'application/json'
        ])->post($this->endpoint . '/sms/send', [
            'sender' => $sender,
            'recipient' => $to,
            'content' => $message
        ]);

        return $response->json();
    }

    /**
     * Handle incoming webhook from PureSMS
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

        return response()->json(['message' => 'Webhook processed'], 200);
    }
}
