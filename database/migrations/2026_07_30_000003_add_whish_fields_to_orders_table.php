<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('whish_external_id')->nullable()->unique()->after('payment_status');
            $table->string('whish_transaction_id')->nullable()->after('whish_external_id');
            $table->text('whish_collect_url')->nullable()->after('whish_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['whish_external_id', 'whish_transaction_id', 'whish_collect_url']);
        });
    }
};
