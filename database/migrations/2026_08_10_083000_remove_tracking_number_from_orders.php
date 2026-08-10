<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tracking_number');
        });

        $setting = DB::table('settings')->where('key', 'packing_slip_fields')->first();
        if ($setting && is_string($setting->value)) {
            $fields = json_decode($setting->value, true);
            if (is_array($fields)) {
                $fields = array_values(array_filter($fields, fn ($field) => $field !== 'tracking'));
                DB::table('settings')->where('key', 'packing_slip_fields')->update([
                    'value' => json_encode($fields),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tracking_number')->nullable()->after('cancelled_at');
        });
    }
};
