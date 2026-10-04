<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SenderAccount extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'from_name',
        'smtp_host',
        'smtp_port',
        'smtp_scheme',
        'smtp_username',
        'smtp_password',
        'daily_limit',
        'enabled',
        'last_used_at',
    ];

    protected $hidden = ['smtp_password'];

    protected function casts(): array
    {
        return [
            'smtp_password' => 'encrypted',
            'smtp_port' => 'integer',
            'daily_limit' => 'integer',
            'enabled' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OutreachMessage::class);
    }

    public function isConfigured(): bool
    {
        return filled($this->email)
            && filled($this->smtp_host)
            && filled($this->smtp_username)
            && filled($this->smtp_password);
    }
}
