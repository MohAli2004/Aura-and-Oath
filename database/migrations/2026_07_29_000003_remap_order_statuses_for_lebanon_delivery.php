<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'ready_for_dispatch' => 'preparing',
            'shipped' => 'on_the_way',
            'completed' => 'delivered',
        ];

        foreach ($map as $from => $to) {
            DB::table('orders')->where('status', $from)->update(['status' => $to]);
            DB::table('order_status_histories')->where('from_status', $from)->update(['from_status' => $to]);
            DB::table('order_status_histories')->where('to_status', $from)->update(['to_status' => $to]);
        }
    }

    public function down(): void
    {
        // Irreversible data remap — previous distinct statuses cannot be restored reliably.
    }
};
