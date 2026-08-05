<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Attribute extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'is_variant',
        'is_filterable',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_variant' => 'boolean',
            'is_filterable' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Attribute $attribute) {
            if (empty($attribute->slug)) {
                $attribute->slug = Str::slug($attribute->name);
            }
        });
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('sort_order');
    }

    /**
     * @return list<string>
     */
    public static function measureUnits(): array
    {
        $values = static::query()
            ->where(function ($query) {
                $query->where('slug', 'unit')->orWhere('type', 'unit');
            })
            ->with('values')
            ->get()
            ->flatMap(fn (self $attribute) => $attribute->values->pluck('value'))
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $values !== [] ? $values : ['ml', 'g'];
    }
}
