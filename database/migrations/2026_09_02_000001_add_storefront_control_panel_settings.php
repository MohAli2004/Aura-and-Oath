<?php

use App\Support\SiteOptions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (SiteOptions::fields() as $key => $meta) {
            if (DB::table('settings')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('settings')->insert([
                'group' => $meta['group'],
                'key' => $key,
                'value' => SiteOptions::fieldDefaults()[$key] ?? '',
                'type' => $meta['type'],
                'is_public' => $meta['public'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (SiteOptions::flags() as $key => $default) {
            if (DB::table('settings')->where('key', $key)->exists()) {
                continue;
            }

            $meta = SiteOptions::flagMeta()[$key];

            DB::table('settings')->insert([
                'group' => $meta['group'],
                'key' => $key,
                'value' => $default ? '1' : '0',
                'type' => 'boolean',
                'is_public' => $meta['public'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $keys = array_merge(
            array_keys(SiteOptions::flags()),
            [
                'contact_phone',
                'contact_whatsapp',
                'contact_address',
                'support_hours',
                'social_instagram',
                'social_facebook',
                'social_tiktok',
                'social_youtube',
                'wish_account_name',
                'wish_account_number',
                'wish_instructions',
            ],
        );

        DB::table('settings')->whereIn('key', $keys)->delete();
    }
};
