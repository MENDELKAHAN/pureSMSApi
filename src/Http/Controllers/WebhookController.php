<?php
// namespace Mendelkahan\LaravelPuresms\Http\Controllers;

// use App\Http\Controllers\Controller;  // Import the real Controller
// use Illuminate\Http\Request;
// use App\Services\PureSmsService;

class WebhookController extends Controller
{
    protected $smsService;

    public function __construct(PureSmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    public function handleWebhook(Request $request)
    {
        return $this->smsService->handleWebhook($request);
    }
}
