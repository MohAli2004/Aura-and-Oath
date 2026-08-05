<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            $table->boolean('is_default')->default(false)->after('sort_order');
            $table->index(['product_id', 'is_default', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'is_default', 'sort_order']);
            $table->dropColumn(['sort_order', 'is_default']);
        });
    }
};
