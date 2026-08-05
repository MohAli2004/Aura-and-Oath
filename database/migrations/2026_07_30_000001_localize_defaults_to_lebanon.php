<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('country', 2)->default('LB')->change();
        });

        Schema::table('order_addresses', function (Blueprint $table) {
            $table->string('country', 2)->default('LB')->change();
        });

        DB::table('customer_addresses')->where('country', 'EG')->update(['country' => 'LB']);
        DB::table('order_addresses')->where('country', 'EG')->update(['country' => 'LB']);
    }

    public function down(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('country', 2)->default('EG')->change();
        });

        Schema::table('order_addresses', function (Blueprint $table) {
            $table->string('country', 2)->default('EG')->change();
        });

        DB::table('customer_addresses')->where('country', 'LB')->update(['country' => 'EG']);
        DB::table('order_addresses')->where('country', 'LB')->update(['country' => 'EG']);
    }
};
