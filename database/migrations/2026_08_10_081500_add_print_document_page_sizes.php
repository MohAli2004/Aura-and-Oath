<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rows = [
            [
                'group' => 'print',
                'key' => 'invoice_size',
                'value' => 'A5',
                'type' => 'string',
                'is_public' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'group' => 'print',
                'key' => 'packing_slip_size',
                'value' => 'A4',
                'type' => 'string',
                'is_public' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($rows as $row) {
            if (! DB::table('settings')->where('key', $row['key'])->exists()) {
                DB::table('settings')->insert($row);
            }
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['invoice_size', 'packing_slip_size'])->delete();
    }
};
