<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Suppression extends Model
{
    protected $fillable = [
        'type',
        'value',
        'reason',
        'source',
        'suppressed_at',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'suppressed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function isEmailSuppressed(string $email): bool
    {
        return static::query()
            ->where('type', 'email')
            ->where('value', static::normalizeEmail($email))
            ->whereNull('released_at')
            ->exists();
    }
}
