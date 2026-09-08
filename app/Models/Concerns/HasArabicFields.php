<?php

namespace App\Models\Concerns;

trait HasArabicFields
{
    public function localized(string $field): string
    {
        $en = $this->attributes[$field] ?? null;
        $arField = $field.'_ar';
        $ar = $this->attributes[$arField] ?? null;

        return localized(is_string($en) ? $en : null, is_string($ar) ? $ar : null);
    }
}
