<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Back up the database and uploaded files (finding O1). Runs daily from
 * the scheduler; see README, "Backups".
 */
class BackupRun extends Command
{
    protected $signature = 'mybooks:backup
        {--only-db : Leave out uploaded files}
        {--no-cleanup : Keep old backups this time}';

    protected $description = 'Back up the database and uploaded files to the configured backup disks';

    public function handle(BackupService $backups): int
    {
        try {
            $result = $backups->run((bool) $this->option('only-db'));
        } catch (Throwable $e) {
            Log::error('Backup failed: '.$e->getMessage());
            $this->error('Backup failed: '.$e->getMessage());
            $backups->notifyFailure('MyBooks backup FAILED', 'The backup at '.now()->toDayDateTimeString()." failed:\n\n".$e->getMessage()."\n\nCheck storage/logs and run: php artisan mybooks:backup");

            return self::FAILURE;
        }

        $removed = $this->option('no-cleanup') ? 0 : $backups->cleanup();

        $this->info(sprintf(
            'Backup %s (%s) written to: %s. Old backups removed: %d.',
            $result['file'],
            number_format($result['size'] / 1048576, 1).' MB',
            implode(', ', $result['disks']),
            $removed
        ));

        return self::SUCCESS;
    }
}
