<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Check the newest backup can be opened and holds the database (audit
 * MB-33), on every backup disk: the off-site copy is the one that matters
 * when the server is lost. Runs weekly from the scheduler and emails
 * BACKUP_NOTIFY_EMAIL when it fails, like a failed backup does; see
 * README, "Backups".
 */
class BackupVerify extends Command
{
    protected $signature = 'mybooks:backup:verify
        {--file= : A backup file name (default: the newest)}
        {--disk= : Check only this backup disk (default: every disk in BACKUP_DISKS)}';

    protected $description = 'Check the newest backup on each disk opens and holds the database';

    public function handle(BackupService $backups): int
    {
        $file = $this->option('file') ?: null;
        $failures = [];

        try {
            $disks = $this->option('disk') ? [(string) $this->option('disk')] : $backups->disks();
        } catch (Throwable $e) {
            $disks = [];
            $failures[] = $e->getMessage();
        }

        foreach ($disks as $disk) {
            try {
                $result = $backups->verify($file, $disk);
            } catch (Throwable $e) {
                // Not only RuntimeException: a disk that isn't configured, or
                // a driver that won't start, must still be reported and emailed.
                $failures[] = "\"{$disk}\" disk: ".$e->getMessage();

                continue;
            }

            $this->info(sprintf(
                'Backup %s on "%s" is good: %s MB database dump, %d tables, %d hours old.',
                $result['file'],
                $result['disk'],
                number_format($result['size'] / 1048576, 1),
                $result['tables'],
                $result['age_hours']
            ));
        }

        if ($failures === []) {
            return self::SUCCESS;
        }

        $message = implode("\n", $failures);
        Log::error('Backup check failed: '.$message);
        $this->error('Backup check failed: '.$message);
        $backups->notifyFailure('MyBooks backup check FAILED', 'The backup check at '.now()->toDayDateTimeString()." failed:\n\n".$message
            ."\n\nRun: php artisan mybooks:backup, then php artisan mybooks:backup:verify");

        return self::FAILURE;
    }
}
