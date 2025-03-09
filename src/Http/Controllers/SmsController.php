<?php

namespace Puresms\Laravel\Http\Controllers;

use Illuminate\Routing\Controller;  // Laravel's base controller
use Illuminate\Http\Request;
use Puresms\Laravel\PureSmsService;  // Correct import for your service

class SmsController extends Controller
{
    protected $smsService;

    public function __construct(PureSmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Send a single SMS and return full response details.
     */
    public function sendSms(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'message' => 'required|string',
            'from' => 'nullable|string',
        ]);

        $response = $this->smsService->sendSms(
            $request->input('to'),
            $request->input('message'),
            $request->input('from')
        );

        return response()->json($response);
    }

}