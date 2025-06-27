<?php
namespace Puresms\Laravel\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = [
        'message_id', 'recipient', 'sender', 'recipient_id', 'sender_id', 'content', 'status', 'error_code', 'processed_at', 'delivered_at'
    ];

    public function senderUser()
    {
        return $this->belongsTo(config('puresms.number_model', \App\Models\User::class), 'sender_id');
    }

    public function recipientUser()
    {
        return $this->belongsTo(config('puresms.number_model', \App\Models\User::class), 'recipient_id');
    }

    public function recipient()
    {
        return $this->belongsTo(config('puresms.number_model', \App\Models\User::class), 'recipient_id');
    }
}