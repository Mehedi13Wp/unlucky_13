<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'service_offer',
        'target_industry',
        'target_countries',
        'target_cities',
        'qualification_rules',
        'daily_outreach_limit',
        'status',
        'review_required',
        'auto_send',
        'last_run_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Campaign $campaign): void {
            $campaign->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'target_countries' => 'array',
            'target_cities' => 'array',
            'qualification_rules' => 'array',
            'daily_outreach_limit' => 'integer',
            'status' => CampaignStatus::class,
            'review_required' => 'boolean',
            'auto_send' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
