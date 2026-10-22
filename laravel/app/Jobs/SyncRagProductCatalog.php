<?php

namespace App\Jobs;

use App\Services\RagProductCatalogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncRagProductCatalog implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 180];

    public int $timeout = 210;

    /**
     * Execute the job.
     */
    public function handle(RagProductCatalogService $productCatalog): void
    {
        $productCatalog->sync();
    }
}
