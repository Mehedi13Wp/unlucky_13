<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAutomationSetting extends Model
{
    protected $fillable = [
        'user_id',
        'enabled',
        'global_daily_limit',
        'interval_minutes',
        'session_hours',
        'session_started_at',
        'session_ends_at',
        'last_attempt_at',
        'next_attempt_at',
        'last_sent_at',
        'last_status',
        'last_message',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'global_daily_limit' => 'integer',
            'interval_minutes' => 'integer',
            'session_hours' => 'integer',
            'session_started_at' => 'datetime',
            'session_ends_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'next_attempt_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
