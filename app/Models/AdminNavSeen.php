<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminNavSeen extends Model
{
    protected $fillable = [
        'user_id',
        'section',
        'seen_at',
    ];

    protected function casts(): array
    {
        return [
            'seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
