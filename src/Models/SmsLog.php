<?php
namespace Puresms\Laravel\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = [
        'message_id', 'recipient', 'sender', 'content', 'status', 'error_code', 'processed_at', 'delivered_at'
    ];
}
