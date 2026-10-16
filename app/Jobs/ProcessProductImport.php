<?php

namespace App\Jobs;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Services\ProductImport\ProductImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

class ProcessProductImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public int $batchId) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('product-import-'.$this->batchId))->expireAfter(900)];
    }

    public function handle(ProductImportService $imports): void
    {
        $batch = ImportBatch::query()->find($this->batchId);
        if (! $batch) {
            return;
        }

        $imports->commit($batch);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('product_import.job_failed', [
            'batch_id' => $this->batchId,
            'message' => $exception->getMessage(),
        ]);

        ImportBatch::query()->whereKey($this->batchId)->update([
            'status' => ImportBatchStatus::Failed->value,
            'error_summary' => 'The import stopped unexpectedly. Products already saved were kept. Retry the batch to continue pending rows.',
            'finished_at' => now(),
        ]);
    }
}
