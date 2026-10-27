<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('delivery_confirmation_due_at')->nullable()->index();
            $table->timestamp('delivery_confirmation_resolved_at')->nullable();
            $table->string('delivery_confirmation_outcome', 24)->nullable();
            $table->decimal('delivery_fee_before', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_confirmation_due_at',
                'delivery_confirmation_resolved_at',
                'delivery_confirmation_outcome',
                'delivery_fee_before',
            ]);
        });
    }
};
