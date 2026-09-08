<?php

namespace App\Models;

use App\Models\Concerns\HasArabicFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AttributeValue extends Model
{
    use HasArabicFields;

    protected $fillable = [
        'attribute_id',
        'value',
        'value_ar',
        'slug',
        'color_hex',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (AttributeValue $value) {
            if (empty($value->slug)) {
                $value->slug = Str::slug($value->value);
            }
        });
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
