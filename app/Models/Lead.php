<?php

namespace App\Models;

use App\Enums\LeadStatus;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'business_name',
        'website_url',
        'domain',
        'industry',
        'country',
        'city',
        'source',
        'source_url',
        'status',
        'qualification_score',
        'qualification_reason',
        'observed_signals',
        'analyzed_at',
        'qualified_at',
        'last_contacted_at',
        'replied_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Lead $lead): void {
            $lead->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'qualification_score' => 'integer',
            'observed_signals' => 'array',
            'analyzed_at' => 'datetime',
            'qualified_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
