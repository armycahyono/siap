<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PiketTelegramLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'target_type',
        'recipient_name',
        'chat_id',
        'message',
        'status',
        'response_payload',
    ];
}
