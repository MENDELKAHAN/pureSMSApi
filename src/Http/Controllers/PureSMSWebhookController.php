<?php

namespace Mendelkahan\LaravelPuresms\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PureSmsService;
use App\Http\Controllers\Controller; // <-- THIS LINE IS CRUCIAL

class PureSMSWebhookController extends Controller
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
