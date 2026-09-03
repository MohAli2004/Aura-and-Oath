<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();

            $table->index(['product_id', 'product_variant_id', 'sort_order'], 'product_images_product_variant_sort_index');
        });

        $now = now();

        foreach (DB::table('product_variants')->whereNotNull('image_path')->where('image_path', '!=', '')->get() as $variant) {
            $exists = DB::table('product_images')
                ->where('product_id', $variant->product_id)
                ->where('product_variant_id', $variant->id)
                ->where('path', $variant->image_path)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('product_images')->insert([
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'path' => $variant->image_path,
                'alt' => $variant->name,
                'sort_order' => 0,
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex('product_images_product_variant_sort_index');
            $table->dropConstrainedForeignId('product_variant_id');
        });
    }
};
