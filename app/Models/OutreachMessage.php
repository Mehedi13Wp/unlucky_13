<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutreachMessage extends Model
{
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'outreach_draft_id',
        'lead_id',
        'campaign_id',
        'sender_account_id',
        'sender_address',
        'channel',
        'recipient',
        'subject',
        'body',
        'status',
        'error_message',
        'attempt_count',
        'attempted_at',
        'retry_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'attempted_at' => 'datetime',
            'retry_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function outreachDraft(): BelongsTo
    {
        return $this->belongsTo(OutreachDraft::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function senderAccount(): BelongsTo
    {
        return $this->belongsTo(SenderAccount::class);
    }
}
