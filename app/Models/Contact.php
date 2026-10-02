<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    protected $fillable = [
        'lead_id',
        'type',
        'value',
        'label',
        'source_url',
        'discovered_at',
    ];

    protected function casts(): array
    {
        return [
            'discovered_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
