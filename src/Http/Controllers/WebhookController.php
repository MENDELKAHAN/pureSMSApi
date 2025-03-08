<?php
// namespace Mendelkahan\Laravelpuresms\Http\Controllers;

// namespace Puresms\LaravelMendelkahan\LaravelPuresms\Http\Controllers;
namespace App\Http\Controllers;

use App\Http\Controllers;  
use Illuminate\Http\Request;
use App\Services\PureSmsService;


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
