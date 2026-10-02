<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutreachDraft extends Model
{
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

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
}
