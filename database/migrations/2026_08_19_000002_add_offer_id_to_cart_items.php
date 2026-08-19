<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cart_items', 'offer_id')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->foreignId('offer_id')->nullable()->after('product_variant_id')->constrained()->nullOnDelete();
            });
        }

        Schema::table('cart_items', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('cart_items'))->pluck('name');

            if (! $indexes->contains('cart_items_cart_id_index') && ! $indexes->contains('cart_items_cart_id_foreign')) {
                $table->index('cart_id');
            }
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_item_unique');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->unique(['cart_id', 'product_id', 'product_variant_id', 'offer_id'], 'cart_item_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_item_unique');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->unique(['cart_id', 'product_id', 'product_variant_id'], 'cart_item_unique');
        });

        if (Schema::hasColumn('cart_items', 'offer_id')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('offer_id');
            });
        }
    }
};
