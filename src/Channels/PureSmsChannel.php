<?php

// namespace Puresms\Laravel\Channels;

// use Illuminate\Notifications\Notification;
// use Puresms\Laravel\Facades\PureSms;
// use Puresms\Laravel\Models\SmsLog;
// use Illuminate\Support\Facades\Log;

// class PureSmsChannel
// {
//     public function send($notifiable, Notification $notification)
//     {
//         // Get the phone number and model ID for logging
//         $recipientPhone = $notifiable->sms_number ?? null;
//         $recipientId = $notifiable->id;

//         // Get the message content and custom sender from the notification
//         $data = $notification->toSms($notifiable);
//         $message = $data['content'] ?? null;
//         $from = $data['from'] ?? null;

//         $response = PureSms::sendSms($recipientPhone, $message, $from, $recipientId);

//         return $response;
//     }
// }





class PureSmsChannel
{
    /**
     * Send the given notification.
     * 
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        // Get the phone number field from config (defaults to 'sms_number')
        $phoneField = config('puresms.mobile_number', 'sms_number');
        
        // Get the phone number and model ID for logging
        $recipientPhone = $notifiable->$phoneField ?? null;
        $recipientId = $notifiable->id; // Assuming the notifiable model has an id attribute

        // Get the message content from the notification
        $message = $notification->toSms($notifiable);

        // Send SMS using the PureSms package, passing recipient ID
        $response = PureSms::sendSms($recipientPhone, $message, null, $recipientId);
    }
}
