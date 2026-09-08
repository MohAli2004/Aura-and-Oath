<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->text('short_description_ar')->nullable()->after('short_description');
            $table->text('description_ar')->nullable()->after('description');
            $table->text('ingredients_ar')->nullable()->after('ingredients');
            $table->text('how_to_use_ar')->nullable()->after('how_to_use');
            $table->string('meta_title_ar')->nullable()->after('meta_title');
            $table->string('meta_description_ar')->nullable()->after('meta_description');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->text('description_ar')->nullable()->after('description');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->text('description_ar')->nullable()->after('description');
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->string('title_ar')->nullable()->after('title');
            $table->text('description_ar')->nullable()->after('description');
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->string('title_ar')->nullable()->after('title');
            $table->string('subtitle_ar')->nullable()->after('subtitle');
            $table->string('button_text_ar')->nullable()->after('button_text');
        });

        Schema::table('attributes', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
        });

        Schema::table('attribute_values', function (Blueprint $table) {
            $table->string('value_ar')->nullable()->after('value');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->string('title_ar')->nullable()->after('title');
            $table->text('content_ar')->nullable()->after('content');
            $table->string('meta_title_ar')->nullable()->after('meta_title');
            $table->string('meta_description_ar')->nullable()->after('meta_description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'name_ar',
                'short_description_ar',
                'description_ar',
                'ingredients_ar',
                'how_to_use_ar',
                'meta_title_ar',
                'meta_description_ar',
            ]);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('name_ar');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'description_ar']);
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'description_ar']);
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['title_ar', 'description_ar']);
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['title_ar', 'subtitle_ar', 'button_text_ar']);
        });

        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('name_ar');
        });

        Schema::table('attribute_values', function (Blueprint $table) {
            $table->dropColumn('value_ar');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['title_ar', 'content_ar', 'meta_title_ar', 'meta_description_ar']);
        });
    }
};
