<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class InventoryLock
{
    public const SESSION_KEY = 'admin.inventory_unlocked_until';

    public const TTL_MINUTES = 15;

    public static function isUnlocked(): bool
    {
        $until = session(self::SESSION_KEY);

        if (! $until) {
            return false;
        }

        try {
            return Carbon::parse($until)->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }

    public static function unlock(): void
    {
        session([self::SESSION_KEY => now()->addMinutes(self::TTL_MINUTES)->toIso8601String()]);
    }

    public static function lock(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public static function touch(): void
    {
        if (self::isUnlocked()) {
            self::unlock();
        }
    }

    public static function unlockedUntil(): ?Carbon
    {
        $until = session(self::SESSION_KEY);

        if (! $until) {
            return null;
        }

        try {
            return Carbon::parse($until);
        } catch (\Throwable) {
            return null;
        }
    }
}
