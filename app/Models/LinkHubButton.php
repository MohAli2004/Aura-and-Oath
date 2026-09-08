<?php

namespace App\Models;

use App\Models\Concerns\HasArabicFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LinkHubButton extends Model
{
    use HasArabicFields;

    protected $fillable = [
        'label',
        'label_ar',
        'url',
        'sort_order',
        'is_active',
        'open_in_new_tab',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'open_in_new_tab' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function isVisible(): bool
    {
        return $this->is_active && trim($this->url) !== '';
    }

    public function href(): string
    {
        return safe_href($this->url, route('home'));
    }
}
