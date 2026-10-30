<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Check the newest backup can be opened and holds the database (audit
 * MB-33). Runs weekly from the scheduler and emails BACKUP_NOTIFY_EMAIL
 * when it fails, like a failed backup does; see README, "Backups".
 */
class BackupVerify extends Command
{
    protected $signature = 'mybooks:backup:verify
        {--file= : A backup file name (default: the newest)}
        {--disk= : The backup disk to check (default: the first in BACKUP_DISKS)}';

    protected $description = 'Check the newest backup opens and holds the database';

    public function handle(BackupService $backups): int
    {
        try {
            $result = $backups->verify($this->option('file') ?: null, $this->option('disk') ?: null);
        } catch (RuntimeException $e) {
            Log::error('Backup check failed: '.$e->getMessage());
            $this->error('Backup check failed: '.$e->getMessage());
            $backups->notifyFailure('MyBooks backup check FAILED', 'The backup check at '.now()->toDayDateTimeString()." failed:\n\n".$e->getMessage()
                ."\n\nRun: php artisan mybooks:backup, then php artisan mybooks:backup:verify");

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Backup %s on "%s" is good: %s MB database dump, %d tables, %d hours old.',
            $result['file'],
            $result['disk'],
            number_format($result['size'] / 1048576, 1),
            $result['tables'],
            $result['age_hours']
        ));

        return self::SUCCESS;
    }
}
