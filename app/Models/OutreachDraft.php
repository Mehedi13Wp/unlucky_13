<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OutreachDraft extends Model
{
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';

    protected $fillable = [
        'lead_id',
        'contact_id',
        'channel',
        'recipient',
        'subject',
        'body',
        'personalization_notes',
        'status',
        'generated_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'personalization_notes' => 'array',
            'generated_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function message(): HasOne
    {
        return $this->hasOne(OutreachMessage::class);
    }
}
