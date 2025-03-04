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
        // Get the recipient phone number from the model (assuming `sms_number` exists)
        $recipient = $notifiable->sms_number ?? null;

        // Get the message content from the notification
        $message = method_exists($notification, 'toSms') ? $notification->toSms($notifiable) : '';

        // Ensure we have a recipient and a message
        if (!$recipient || !$message) {
            throw new \Exception("Recipient or message is missing for SMS notification.");
        }

        // Send SMS using the PureSms package
        $response = PureSms::sendSms($recipient, $message);

        // Store the message in the database
        SmsLog::create([
            'message_id' => $response['id'] ?? null,
            'recipient'  => $recipient,
            'sender'     => config('puresms.sender_name', 'ConnectTest'),
            'content'    => $message,
            'status'     => 'pending', // Default status
            'error_code' => $response['error_code'] ?? null,
            'processed_at' => now(),
        ]);
    }
}
