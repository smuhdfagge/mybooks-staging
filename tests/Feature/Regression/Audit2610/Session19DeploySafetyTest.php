<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Services\BackupService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

/**
 * October 2026 audit, fix session 19 (deploy safety):
 * - MB-25: deploys migrated with no backup and no way back;
 * - MB-33: whether a backup could be restored was only ever checked by
 *   hand, once a quarter.
 * The CI gates (npm audit, bash -n on the scripts) are in php.yml.
 */
class Session19DeploySafetyTest extends TestCase
{
    private string $work;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mybooks.backup.disks' => ['backups'], 'mybooks.backup.password' => null, 'mybooks.backup.notify' => 'ops@example.com']);
        Storage::fake('backups');
        $this->work = storage_path('framework/testing/s19-'.uniqid());
        File::ensureDirectoryExists($this->work);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->work);
        parent::tearDown();
    }

    /** A mysqldump-style dump with CREATE TABLE for $tables. */
    private function mysqlDump(array $tables): string
    {
        $sql = "-- MariaDB dump 10.19\n/*!40101 SET NAMES utf8mb4 */;\n";
        foreach ($tables as $table) {
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\nCREATE TABLE `{$table}` (\n  `id` bigint unsigned NOT NULL AUTO_INCREMENT,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB;\nINSERT INTO `{$table}` VALUES (1);\n";
        }

        return $sql;
    }

    /** An SQLite database file holding $tables, as SQLite backups do. */
    private function sqliteCopy(array $tables): string
    {
        $path = $this->work.'/copy.sqlite';
        @unlink($path);
        $pdo = new PDO('sqlite:'.$path);
        foreach ($tables as $table) {
            $pdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY)");
        }
        $pdo = null;

        return (string) file_get_contents($path);
    }

    /** Put a backup zip holding $dump as database.sql on the fake disk. */
    private function backup(string $name, ?string $dump, ?string $password = null, int $hoursOld = 1): string
    {
        $zipPath = $this->work.'/'.$name;
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($dump !== null) {
            $zip->addFromString('database.sql', $dump);
            if ($password !== null) {
                $zip->setPassword($password);
                $zip->setEncryptionName('database.sql', ZipArchive::EM_AES_256);
            }
        } else {
            $zip->addFromString('files/readme.txt', 'no dump here');
        }
        $zip->close();

        $file = BackupService::FOLDER.'/'.$name;
        Storage::disk('backups')->put($file, (string) file_get_contents($zipPath));
        touch(Storage::disk('backups')->path($file), now()->subHours($hoursOld)->getTimestamp());

        return $file;
    }

    private function failsWith(string $message, ?string $file = null): void
    {
        try {
            app(BackupService::class)->verify($file);
            $this->fail("verify passed; expected: {$message}");
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    public function test_a_mysqldump_backup_with_the_core_tables_passes(): void
    {
        $this->backup('mybooks-2026-10-29-013000.zip', $this->mysqlDump(['tenants']), hoursOld: 25);
        $this->backup('mybooks-2026-10-30-013000.zip', $this->mysqlDump([...BackupService::CORE_TABLES, 'items']));

        $result = app(BackupService::class)->verify();

        $this->assertSame('mybooks-2026-10-30-013000.zip', $result['file'], 'the newest');
        $this->assertSame(count(BackupService::CORE_TABLES) + 1, $result['tables']);
        $this->assertSame(1, $result['age_hours']);
    }

    public function test_an_sqlite_copy_with_the_core_tables_passes(): void
    {
        $this->backup('mybooks-2026-10-30-013000.zip', $this->sqliteCopy(BackupService::CORE_TABLES));

        $this->assertSame(count(BackupService::CORE_TABLES), app(BackupService::class)->verify()['tables']);
    }

    public function test_an_encrypted_backup_opens_with_the_password_and_not_without(): void
    {
        $file = $this->backup('mybooks-2026-10-30-013000.zip', $this->mysqlDump(BackupService::CORE_TABLES), password: 'correct horse');

        config(['mybooks.backup.password' => 'correct horse']);
        $this->assertSame(count(BackupService::CORE_TABLES), app(BackupService::class)->verify()['tables']);

        config(['mybooks.backup.password' => 'wrong']);
        $this->failsWith('is BACKUP_ARCHIVE_PASSWORD the one it was made with');

        config(['mybooks.backup.password' => null]);
        $this->failsWith('it may be encrypted: set BACKUP_ARCHIVE_PASSWORD');
    }

    public function test_a_backup_missing_tables_is_refused_and_names_them(): void
    {
        $this->backup('mybooks-2026-10-30-013000.zip', $this->mysqlDump(['tenants', 'users', 'chart_of_accounts', 'journals', 'journal_entries', 'customers']));

        $this->failsWith('the database dump has no invoices, bills tables.');
    }

    public function test_an_empty_dump_a_missing_dump_and_a_damaged_zip_are_refused(): void
    {
        $this->backup('empty.zip', '');
        $this->failsWith('the database dump is empty', 'empty.zip');

        $this->backup('nodump.zip', null);
        $this->failsWith('has no database.sql', 'nodump.zip');

        Storage::disk('backups')->put(BackupService::FOLDER.'/broken.zip', 'not a zip at all');
        $this->failsWith('damaged or not a zip', 'broken.zip');

        $this->failsWith('There is no backup called', 'nope.zip');
    }

    public function test_no_backups_or_only_an_old_one_is_refused(): void
    {
        $this->failsWith('There is no backup on the "backups" disk.');

        $this->backup('mybooks-2026-10-27-013000.zip', $this->mysqlDump(BackupService::CORE_TABLES), hoursOld: 72);
        $this->failsWith('is the newest backup and it is 72 hours old');
    }

    public function test_the_command_reports_and_emails_a_failure(): void
    {
        $this->backup('mybooks-2026-10-30-013000.zip', $this->mysqlDump(['tenants']));
        Mail::shouldReceive('raw')->once()->withArgs(fn (string $body) => str_contains($body, 'the database dump has no users'));

        $this->artisan('mybooks:backup:verify')->assertFailed()->expectsOutputToContain('Backup check failed');
    }

    public function test_the_command_says_a_good_backup_is_good(): void
    {
        $this->backup('mybooks-2026-10-30-013000.zip', $this->mysqlDump(BackupService::CORE_TABLES));
        Mail::shouldReceive('raw')->never();

        $this->artisan('mybooks:backup:verify')->assertSuccessful()
            ->expectsOutputToContain('Backup mybooks-2026-10-30-013000.zip on "backups" is good');
    }

    public function test_the_check_runs_weekly(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'mybooks:backup:verify'));

        $this->assertNotNull($event, 'mybooks:backup:verify is scheduled');
        $this->assertSame('15 3 * * 1', $event->expression);
    }

    /**
     * Run deploy.sh in a throwaway clone with a stand-in PHP that logs what
     * it is asked to do, and fails the backup when told to.
     *
     * @return array{0: Process, 1: list<string>, 2: string}
     */
    private function deploy(bool $backupFails): array
    {
        $root = $this->work.'/deploy';
        $origin = $root.'/origin.git';
        $app = $root.'/app';
        $log = $root.'/calls.log';
        File::ensureDirectoryExists($app.'/scripts');
        copy(base_path('scripts/deploy.sh'), $app.'/scripts/deploy.sh');

        $fakePhp = $root.'/php';
        file_put_contents($fakePhp, "#!/usr/bin/env bash\necho \"\$*\" >> ".escapeshellarg($log)."\n"
            .'if [ "$2" = "mybooks:backup" ]; then '.($backupFails ? 'echo "Backup failed: disk full"; exit 1' : 'echo "Backup mybooks-2026-10-30-091500.zip (0.1 MB) written to: backups. Old backups removed: 0."').'; fi'."\n");
        chmod($fakePhp, 0755);

        $sh = fn (string $cmd, string $cwd) => Process::fromShellCommandline($cmd, $cwd)->mustRun();
        $sh('git init -q --bare -b main '.escapeshellarg($origin), $root);
        $sh('git init -q -b main && git config user.email t@example.com && git config user.name t && git add . && git commit -qm first'
            .' && git remote add origin '.escapeshellarg($origin).' && git push -q origin main', $app);
        $before = trim($sh('git rev-parse HEAD', $app)->getOutput());
        // A newer commit on the remote for the deploy to bring in.
        $sh('git clone -q '.escapeshellarg($origin).' next && cd next && git config user.email t@example.com && git config user.name t'
            .' && echo v2 > VERSION && git add . && git commit -qm second && git push -q origin main', $root);

        $process = new Process(['bash', 'scripts/deploy.sh'], $app, ['PHP' => $fakePhp, 'COMPOSER_BIN' => '/bin/true', 'GIT_TERMINAL_PROMPT' => '0']);
        $process->run();

        return [$process, is_file($log) ? file($log, FILE_IGNORE_NEW_LINES) : [], $before];
    }

    public function test_deploy_backs_up_first_and_records_where_it_started(): void
    {
        [$process, $calls, $before] = $this->deploy(backupFails: false);

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        $this->assertSame('artisan mybooks:backup --only-db --no-cleanup', $calls[0], 'the backup is the first thing it does');
        $backup = array_search('artisan mybooks:backup --only-db --no-cleanup', $calls, true);
        $migrate = array_search('artisan migrate --force', $calls, true);
        $this->assertNotFalse($migrate);
        $this->assertLessThan($migrate, $backup);

        $record = (string) file_get_contents($this->work.'/deploy/app/storage/app/last-deploy-from');
        $this->assertStringContainsString("commit={$before}\n", $record);
        $this->assertStringContainsString("backup=mybooks-2026-10-30-091500.zip\n", $record);
        $this->assertFileExists($this->work.'/deploy/app/VERSION', 'the new code came in');
        $this->assertStringContainsString('To undo: docs/ROLLBACK.md', $process->getOutput());
    }

    public function test_deploy_stops_before_changing_anything_when_the_backup_fails(): void
    {
        [$process, $calls] = $this->deploy(backupFails: true);

        $this->assertSame(1, $process->getExitCode());
        $this->assertSame(['artisan mybooks:backup --only-db --no-cleanup'], $calls, 'no maintenance mode, no migrate');
        $this->assertStringContainsString('The backup failed, so nothing was deployed', $process->getErrorOutput());
        $this->assertStringContainsString('disk full', $process->getErrorOutput());
        $this->assertFileDoesNotExist($this->work.'/deploy/app/VERSION', 'the code was not pulled');
        $this->assertFileDoesNotExist($this->work.'/deploy/app/storage/app/last-deploy-from');
    }
}
