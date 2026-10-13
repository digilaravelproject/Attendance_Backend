<?php

namespace App\Console\Commands;

use App\Services\WebsiteLeadSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncWebsiteLeads extends Command
{
    protected $signature = 'leads:sync-website';

    protected $description = 'Import new and updated leads from the configured website endpoint';

    public function handle(WebsiteLeadSyncService $service): int
    {
        try {
            $result = $service->sync();
            $this->info("Website leads synced: {$result['created']} created, {$result['updated']} refreshed, {$result['processed']} processed.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Website lead sync failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
