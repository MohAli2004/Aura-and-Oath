<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_nav_seens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('section', 64);
            $table->timestamp('seen_at');
            $table->timestamps();

            $table->unique(['user_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_nav_seens');
    }
};
