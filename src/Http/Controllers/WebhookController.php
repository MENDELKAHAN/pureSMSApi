<?php

namespace Puresms\Laravel\Http\Controllers;

use Illuminate\Routing\Controller;  // Laravel's base controller
use Illuminate\Http\Request;
use Puresms\Laravel\PureSmsService;  // Correct import for your service




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
