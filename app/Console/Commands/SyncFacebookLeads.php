<?php

namespace App\Console\Commands;

use App\FacebookLeadSyncService;
use App\Models\FacebookIntegration;
use Illuminate\Console\Command;
use Throwable;

class SyncFacebookLeads extends Command
{
    protected $signature = 'leads:sync-facebook {--integration= : Sync one integration ID only}';

    protected $description = 'Import Facebook leads from all forms on configured active pages';

    public function handle(FacebookLeadSyncService $service): int
    {
        $query = FacebookIntegration::query()
            ->whereIn('status', ['active', 'sync_failed'])
            ->whereNotNull('page_access_token');
        if ($this->option('integration')) {
            $query->whereKey($this->option('integration'));
        }

        $failed = false;
        $query->each(function (FacebookIntegration $integration) use ($service, &$failed) {
            try {
                $result = $service->sync($integration);
                $this->info("Facebook integration {$integration->id}: {$result['created']} created, {$result['updated']} refreshed, {$result['processed']} processed.");
            } catch (Throwable $exception) {
                report($exception);
                $failed = true;
                $this->error("Facebook integration {$integration->id} failed: {$exception->getMessage()}");
            }
        });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
