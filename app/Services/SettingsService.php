<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    protected const CACHE_KEY = 'aura.settings';

    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::query()->get()->mapWithKeys(fn (Setting $s) => [
                $s->key => $s->castedValue(),
            ])->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return $all[$key] ?? $default;
    }

    public function set(string $key, mixed $value, string $type = 'string', string $group = 'general', bool $isPublic = false): Setting
    {
        $stored = match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };

        $setting = Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $stored,
                'type' => $type,
                'group' => $group,
                'is_public' => $isPublic,
            ]
        );

        $this->clear();

        return $setting;
    }

    public function clear(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function group(string $group): array
    {
        return Setting::query()
            ->where('group', $group)
            ->get()
            ->mapWithKeys(fn (Setting $s) => [$s->key => $s->castedValue()])
            ->all();
    }
}
