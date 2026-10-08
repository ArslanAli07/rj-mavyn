<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationOutbox extends Model
{
    protected $table = 'notification_outbox';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'attempts' => 'integer', 'available_at' => 'datetime',
            'queued_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
