<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_hub_settings', function (Blueprint $table) {
            $table->id();
            $table->string('headline');
            $table->string('headline_ar')->nullable();
            $table->text('body')->nullable();
            $table->text('body_ar')->nullable();
            $table->timestamps();
        });

        Schema::create('link_hub_buttons', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('label_ar')->nullable();
            $table->string('url');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('open_in_new_tab')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('link_hub_settings')->insert([
            'headline' => (string) config('aura.name', 'Aura & Oath'),
            'headline_ar' => null,
            'body' => (string) config('aura.tagline', ''),
            'body_ar' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $instagram = trim((string) config('aura.social.instagram', ''));

        DB::table('link_hub_buttons')->insert([
            [
                'label' => 'Shop',
                'label_ar' => 'تسوق',
                'url' => '/',
                'sort_order' => 0,
                'is_active' => true,
                'open_in_new_tab' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'label' => 'Instagram',
                'label_ar' => 'إنستغرام',
                'url' => $instagram !== '' ? $instagram : 'https://instagram.com/auraandoath',
                'sort_order' => 1,
                'is_active' => true,
                'open_in_new_tab' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('link_hub_buttons');
        Schema::dropIfExists('link_hub_settings');
    }
};
