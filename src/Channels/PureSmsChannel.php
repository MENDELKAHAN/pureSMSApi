<?php
namespace Puresms\Laravel\Channels; // Fix namespace

use Illuminate\Notifications\Notification;
use Puresms\Laravel\Facades\PureSms;
use Puresms\Laravel\Models\SmsLog; // Fix namespace

class PureSmsChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     */
    public function send($notifiable, Notification $notification)
    {
        // Get the phone number (for sending) and also the model ID for logging
        $recipientPhone = $notifiable->sms_number ?? null;
        $recipientId = $notifiable->id; // assuming the notifiable model has an id attribute

        // Get the message content from the notification
        $message = $notification->toSms($notifiable);

        // Send SMS using the PureSms package, passing recipient id as a new parameter
        $response = PureSms::sendSms($recipientPhone, $message, null, $recipientId);
    }
}
