<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscoveryDomain extends Model
{
    protected $fillable = [
        'domain',
        'canonical_url',
        'first_lead_id',
        'first_campaign_id',
        'source',
        'source_url',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function firstLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'first_lead_id');
    }

    public function firstCampaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'first_campaign_id');
    }
}
