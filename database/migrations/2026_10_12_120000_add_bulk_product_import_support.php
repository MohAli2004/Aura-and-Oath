<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('schema_version', 20)->nullable();
            $table->string('idempotency_key', 64)->index();
            $table->string('status', 32)->default('validated')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->text('error_summary')->nullable();
            $table->json('source_meta')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_index');
            $table->string('external_key')->nullable()->index();
            $table->string('name')->nullable();
            $table->json('payload');
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->json('missing_fields')->nullable();
            $table->string('match_type', 32)->nullable();
            $table->foreignId('matched_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('resolution', 32)->default('review');
            $table->string('status', 32)->default('pending')->index();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['import_batch_id', 'row_index']);
        });

        Schema::create('import_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('match_status', 32)->default('unmatched')->index();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('suggested_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('content_approved_at')->nullable()->after('published_at');
            $table->string('content_source', 32)->nullable()->after('content_approved_at')->index();
            $table->foreignId('import_batch_id')->nullable()->after('brand_id')->constrained('import_batches')->nullOnDelete();
            $table->json('readiness_issues')->nullable();
            $table->json('import_meta')->nullable();
            $table->boolean('is_publishable')->default(false)->index();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->nullable()->change();
            $table->unsignedInteger('stock_quantity')->nullable()->default(null)->change();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('stock_quantity')->nullable()->default(null)->change();
        });

        DB::table('products')->whereNull('content_approved_at')->update([
            'content_source' => 'verified',
            'content_approved_at' => DB::raw('COALESCE(published_at, created_at)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_batch_id');
            $table->dropColumn([
                'content_approved_at',
                'content_source',
                'readiness_issues',
                'import_meta',
                'is_publishable',
            ]);
        });

        Schema::dropIfExists('import_media');
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
    }
};
