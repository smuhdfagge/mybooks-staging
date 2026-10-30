<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Database and file backups (finding O1).
 *
 * Makes one zip holding a database dump and the uploaded files, writes it
 * to every disk in mybooks.backup.disks, and removes copies older than
 * mybooks.backup.keep_days. No extra packages: mysqldump for MySQL and
 * MariaDB, a file copy for SQLite, PHP's ZipArchive for the archive.
 *
 * For an off-site copy, add an S3-compatible disk (Cloudflare R2,
 * Backblaze B2, AWS) in config/filesystems.php and list it in
 * BACKUP_DISKS. See README, "Backups".
 */
class BackupService
{
    public const FOLDER = 'mybooks-backups';

    /** Tables a backup has to hold to be any use (verify()). */
    public const CORE_TABLES = ['tenants', 'users', 'chart_of_accounts', 'journals', 'journal_entries', 'customers', 'invoices', 'bills'];

    /** A newest backup older than this means the nightly one has stopped. */
    public const MAX_AGE_HOURS = 48;

    /**
     * Run a backup. Returns the file name written to each disk.
     *
     * @return array{file: string, size: int, disks: array<int, string>}
     */
    public function run(bool $databaseOnly = false): array
    {
        $disks = $this->disks();
        $work = storage_path('app/backup-temp/'.uniqid('run-', true));
        File::ensureDirectoryExists($work);

        try {
            $dumpPath = $work.'/database.sql';
            $this->dumpDatabase($dumpPath);

            if (! is_file($dumpPath) || filesize($dumpPath) === 0) {
                throw new RuntimeException('The database dump is empty.');
            }

            $name = str()->slug(config('app.name', 'mybooks')).'-'.now()->format('Y-m-d-His').'.zip';
            $zipPath = $work.'/'.$name;
            $this->makeZip($zipPath, $dumpPath, $databaseOnly ? [] : $this->fileFolders());

            foreach ($disks as $disk) {
                $stream = fopen($zipPath, 'rb');
                try {
                    Storage::disk($disk)->writeStream(self::FOLDER.'/'.$name, $stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }

            $size = filesize($zipPath);
            $this->recordSuccess($name, $size, $disks);

            return ['file' => $name, 'size' => $size, 'disks' => $disks];
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * Delete backups older than keep_days from every disk, always keeping
     * the newest one. Returns the number of files removed.
     */
    public function cleanup(): int
    {
        $cutoff = now()->subDays(max(1, (int) config('mybooks.backup.keep_days', 30)))->getTimestamp();
        $removed = 0;

        foreach ($this->disks() as $disk) {
            $storage = Storage::disk($disk);
            $files = collect($storage->files(self::FOLDER))
                ->filter(fn ($f) => str_ends_with($f, '.zip'))
                ->sortByDesc(fn ($f) => $storage->lastModified($f))
                ->values();

            foreach ($files->slice(1) as $file) {
                if ($storage->lastModified($file) < $cutoff) {
                    $storage->delete($file);
                    $removed++;
                }
            }
        }

        return $removed;
    }

    /**
     * Open a backup and check it holds the database (audit MB-33: the only
     * check was a restore test by hand, once a quarter). The newest on
     * $disk (default: the first backup disk) unless $file is named; the
     * command checks every disk.
     *
     * The archive has to open (with BACKUP_ARCHIVE_PASSWORD when set) and
     * hold a database.sql that isn't empty and has every one of
     * CORE_TABLES: CREATE TABLE statements in a mysqldump, or the tables
     * themselves in an SQLite copy. The newest backup must also be less
     * than MAX_AGE_HOURS old. It does not load the dump anywhere: a full
     * restore test is still worth doing now and then (README, "Backups").
     *
     * @return array{file: string, disk: string, size: int, tables: int, age_hours: int}
     *
     * @throws RuntimeException saying what is wrong
     */
    public function verify(?string $file = null, ?string $disk = null): array
    {
        $disk ??= $this->disks()[0];
        $storage = Storage::disk($disk);

        if ($file === null) {
            $file = collect($storage->files(self::FOLDER))
                ->filter(fn ($f) => str_ends_with($f, '.zip'))
                ->sortByDesc(fn ($f) => $storage->lastModified($f))
                ->first();
            if ($file === null) {
                throw new RuntimeException("There is no backup on the \"{$disk}\" disk.");
            }

            $ageHours = (int) floor((now()->getTimestamp() - $storage->lastModified($file)) / 3600);
            if ($ageHours >= self::MAX_AGE_HOURS) {
                throw new RuntimeException(basename($file)." is the newest backup and it is {$ageHours} hours old: the nightly backup has stopped running.");
            }
        } else {
            $file = str_contains($file, '/') ? $file : self::FOLDER.'/'.$file;
            if (! $storage->exists($file)) {
                throw new RuntimeException("There is no backup called {$file} on the \"{$disk}\" disk.");
            }
            $ageHours = (int) floor((now()->getTimestamp() - $storage->lastModified($file)) / 3600);
        }

        $work = storage_path('app/backup-temp/'.uniqid('verify-', true));
        File::ensureDirectoryExists($work);

        try {
            $zipPath = $work.'/backup.zip';
            $in = $storage->readStream($file);
            if (! is_resource($in)) {
                throw new RuntimeException(basename($file).' could not be read from the "'.$disk.'" disk.');
            }
            $out = fopen($zipPath, 'wb');
            $copied = $out === false ? false : stream_copy_to_stream($in, $out);
            if ($out !== false) {
                fclose($out);
            }
            fclose($in);
            if ($copied === false) {
                throw new RuntimeException(basename($file).' could not be copied here to check it (is there room in storage/app?).');
            }
            if ($copied === 0) {
                throw new RuntimeException(basename($file).' is empty (0 bytes): it was cut off while being written or uploaded.');
            }

            $dumpPath = $this->extractDump($zipPath, $work);
            $size = (int) filesize($dumpPath);
            if ($size === 0) {
                throw new RuntimeException(basename($file).': the database dump is empty.');
            }

            $tables = $this->dumpTables($dumpPath);
            $missing = array_values(array_diff(self::CORE_TABLES, $tables));
            if ($missing !== []) {
                throw new RuntimeException(basename($file).': the database dump has no '.implode(', ', $missing).' table'.(count($missing) > 1 ? 's' : '').'.');
            }

            return ['file' => basename($file), 'disk' => $disk, 'size' => $size, 'tables' => count($tables), 'age_hours' => $ageHours];
        } finally {
            File::deleteDirectory($work);
        }
    }

    /** database.sql out of a backup archive, into $work. */
    protected function extractDump(string $zipPath, string $work): string
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('The backup archive will not open: it is damaged or not a zip.');
        }

        try {
            $password = (string) config('mybooks.backup.password', '');
            if ($password !== '') {
                $zip->setPassword($password);
            }
            if ($zip->locateName('database.sql') === false) {
                throw new RuntimeException('The backup archive has no database.sql.');
            }
            if (! $zip->extractTo($work, 'database.sql')) {
                throw new RuntimeException('database.sql could not be read from the backup archive'
                    .($password !== '' ? ' (is BACKUP_ARCHIVE_PASSWORD the one it was made with?).' : ' (it may be encrypted: set BACKUP_ARCHIVE_PASSWORD).'));
            }
        } finally {
            $zip->close();
        }

        return $work.'/database.sql';
    }

    /**
     * The tables in a dump: an SQLite copy (what SQLite backups hold) is
     * opened and asked; a mysqldump is read for its CREATE TABLE lines.
     *
     * @return list<string>
     */
    protected function dumpTables(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('The database dump could not be read.');
        }
        $header = (string) fread($handle, 16);
        fclose($handle);

        if ($header === "SQLite format 3\0") {
            try {
                $pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $statement = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");

                return $statement === false ? [] : array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
            } catch (\PDOException $e) {
                throw new RuntimeException('The SQLite copy in the backup will not open: '.$e->getMessage());
            }
        }

        $tables = [];
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('The database dump could not be read.');
        }
        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^CREATE TABLE (?:IF NOT EXISTS )?`?([A-Za-z0-9_]+)`?/', $line, $m)) {
                $tables[] = $m[1];
            }
        }
        fclose($handle);

        return array_values(array_unique($tables));
    }

    /**
     * Email BACKUP_NOTIFY_EMAIL (default: the support email) that a backup
     * or its check failed. Never throws: the failure is logged either way.
     */
    public function notifyFailure(string $subject, string $body): void
    {
        $to = config('mybooks.backup.notify');
        if (! $to) {
            return;
        }

        try {
            Mail::raw($body, fn ($m) => $m->to($to)->subject($subject));
        } catch (\Throwable $e) {
            Log::error('Could not send the backup failure email: '.$e->getMessage());
        }
    }

    /**
     * When the last backup succeeded, or null if never.
     */
    public function lastSuccess(): ?Carbon
    {
        $path = $this->statusPath();
        if (! is_file($path)) {
            return null;
        }
        $status = json_decode((string) file_get_contents($path), true);

        return isset($status['finished_at']) ? Carbon::parse($status['finished_at']) : null;
    }

    public function dumpDatabase(string $path): void
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        match ($config['driver'] ?? null) {
            'mysql', 'mariadb' => $this->dumpMysql($config, $path),
            'sqlite' => $this->dumpSqlite($config, $path),
            default => throw new RuntimeException("Backups don't support the {$config['driver']} database driver."),
        };
    }

    /** @param array<string, mixed> $config */
    protected function dumpMysql(array $config, string $path): void
    {
        // Credentials go in a private options file, not on the command line,
        // so they don't show up in the server's process list.
        $options = dirname($path).'/my.cnf';
        $lines = ['[client]', 'user="'.addcslashes((string) $config['username'], '"\\').'"', 'password="'.addcslashes((string) $config['password'], '"\\').'"'];
        if (! empty($config['unix_socket'])) {
            $lines[] = 'socket="'.$config['unix_socket'].'"';
        } else {
            $lines[] = 'host="'.$config['host'].'"';
            $lines[] = 'port='.(int) ($config['port'] ?? 3306);
        }
        file_put_contents($options, implode("\n", $lines)."\n");
        chmod($options, 0600);

        $process = new Process([
            config('mybooks.backup.mysqldump', 'mysqldump'),
            '--defaults-extra-file='.$options,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--result-file='.$path,
            $config['database'],
        ]);
        $process->setTimeout(1800);
        $process->run();
        @unlink($options);

        if (! $process->isSuccessful()) {
            throw new RuntimeException('mysqldump failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    /** @param array<string, mixed> $config */
    protected function dumpSqlite(array $config, string $path): void
    {
        $database = $config['database'];
        if ($database === ':memory:') {
            throw new RuntimeException('An in-memory SQLite database cannot be backed up.');
        }
        // VACUUM INTO makes a consistent copy even while the app is writing.
        DB::statement('VACUUM INTO ?', [$path]);
    }

    /**
     * @param  array<string, string>  $folders  name inside the zip => folder on disk
     */
    protected function makeZip(string $zipPath, string $dumpPath, array $folders): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the backup archive.');
        }

        $password = (string) config('mybooks.backup.password', '');
        if ($password !== '') {
            $zip->setPassword($password);
        }

        $add = function (string $file, string $name) use ($zip, $password) {
            $zip->addFile($file, $name);
            if ($password !== '') {
                $zip->setEncryptionName($name, ZipArchive::EM_AES_256);
            }
        };

        $add($dumpPath, 'database.sql');

        foreach ($folders as $label => $folder) {
            if (! is_dir($folder)) {
                continue;
            }
            foreach (File::allFiles($folder, true) as $file) {
                $add($file->getPathname(), 'files/'.$label.'/'.$file->getRelativePathname());
            }
        }

        if (! $zip->close()) {
            throw new RuntimeException('Could not write the backup archive.');
        }
    }

    /** @return array<string, string> */
    protected function fileFolders(): array
    {
        return [
            'private' => storage_path('app/private'),
            'public' => storage_path('app/public'),
            'imports' => storage_path('app/imports'),
        ];
    }

    /** @return array<int, string> */
    public function disks(): array
    {
        $disks = array_values(array_filter(array_map('trim', (array) config('mybooks.backup.disks', ['backups']))));
        if ($disks === []) {
            throw new RuntimeException('No backup disks are configured (BACKUP_DISKS).');
        }

        return $disks;
    }

    /** @param array<int, string> $disks */
    protected function recordSuccess(string $name, int $size, array $disks): void
    {
        File::ensureDirectoryExists(dirname($this->statusPath()));
        file_put_contents($this->statusPath(), json_encode([
            'file' => $name,
            'size' => $size,
            'disks' => $disks,
            'finished_at' => now()->toIso8601String(),
        ]));
    }

    protected function statusPath(): string
    {
        return storage_path('app/backup-status.json');
    }
}
