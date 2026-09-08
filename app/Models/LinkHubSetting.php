<?php

namespace App\Models;

use App\Models\Concerns\HasArabicFields;
use Illuminate\Database\Eloquent\Model;

class LinkHubSetting extends Model
{
    use HasArabicFields;

    protected $fillable = [
        'headline',
        'headline_ar',
        'body',
        'body_ar',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'headline' => (string) config('aura.name', 'Aura & Oath'),
            'body' => (string) config('aura.tagline', ''),
        ]);
    }
}
