<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Finishes a deploy the host started. The host pulls new code into the
 * site's folder when main changes, but runs none of deploy.sh: no backup,
 * no Composer, no migrations, no cache refresh, so the site can break on a
 * stale route cache (November 2026: "Route [partners] not defined").
 *
 * Every minute (scheduler) this compares the code's commit with the one
 * deploy.sh last finished, and runs deploy.sh on the code as it is (no
 * pull of its own) when they differ. Its output goes to
 * storage/logs/auto-deploy.log.
 *
 * It leaves alone:
 *  - code that went back, not forward (a rollback, docs/ROLLBACK.md): the
 *    deployed commit is not an ancestor of the code's, so it waits;
 *  - a branch other than the one configured (main), say a copy of the site
 *    someone checked out to try something;
 *  - a deploy already running (deploy.sh holds a lock, exit 75);
 *  - a commit whose deploy failed: that is reported once, by the next run
 *    (a fresh process, so the alert is not sent by code loaded before
 *    Composer replaced vendor/), and waits for new code or a deploy by hand.
 *
 * No time limit: stopping deploy.sh halfway through a migration would bring
 * the site up on a half-changed database.
 *
 * Production only, and switched off with MYBOOKS_AUTO_DEPLOY=false.
 */
class DeployIfPulled extends Command
{
    protected $signature = 'mybooks:deploy-if-pulled';

    protected $description = 'Run deploy.sh when the host has pulled code that has not been deployed';

    public const DEPLOYED = 'app/deployed-commit';

    public const FAILED = 'app/auto-deploy-failed';

    /** deploy.sh's exit code when another deploy holds the lock. */
    public const BUSY = 75;

    public function handle(): int
    {
        // Defaults here as well as in config: the first time this code is
        // pulled, the cached config predates the auto_deploy section.
        if (! config('mybooks.auto_deploy.enabled', true) || ! app()->environment('production')) {
            return self::SUCCESS;
        }

        $script = (string) config('mybooks.auto_deploy.script', base_path('scripts/deploy.sh'));
        if (! is_file($script) || ! is_dir(base_path('.git'))) {
            return self::SUCCESS;
        }

        $head = $this->git('rev-parse', 'HEAD');
        if ($head === null || ! preg_match('/^[0-9a-f]{40}$/', $head)) {
            $this->warn('Could not read the commit of the code in this folder.');

            return self::SUCCESS;
        }
        if ($this->git('rev-parse', '--abbrev-ref', 'HEAD') !== (string) config('mybooks.auto_deploy.branch', 'main')) {
            return self::SUCCESS;
        }

        $failed = $this->failed();
        if (($failed['commit'] ?? null) === $head) {
            if (! ($failed['reported'] ?? false)) {
                $this->reportFailure($failed);
            }

            return self::SUCCESS; // Waits for new code or a deploy by hand.
        }

        $deployed = $this->read(self::DEPLOYED);
        if ($deployed === $head) {
            return self::SUCCESS;
        }
        if ($deployed !== null && $this->wentBack($deployed)) {
            return self::SUCCESS; // Rolled back: not undone from here.
        }

        $this->info('New code '.substr($head, 0, 7).($deployed ? ' (live: '.substr($deployed, 0, 7).')' : '').': running deploy.sh.');

        $env = [
            'PHP' => PHP_BINARY,
            'GIT_TERMINAL_PROMPT' => '0',
            'DEPLOY_NO_PULL' => '1', // The host pulled already; deploy what is here.
        ];
        if ($composer = config('mybooks.auto_deploy.composer')) {
            $env['COMPOSER_BIN'] = $composer;
        }

        $log = storage_path('logs/auto-deploy.log');
        File::ensureDirectoryExists(dirname($log));
        File::append($log, "\n=== ".now()->toIso8601String().' deploying '.$head."\n");

        $process = new Process(['bash', $script], base_path(), $env, null, null);
        $process->run(function ($type, $buffer) use ($log) {
            File::append($log, $buffer);
        });

        if ($process->isSuccessful()) {
            File::delete(storage_path(self::FAILED));
            $this->info('Deployed '.substr($head, 0, 7).'.');

            return self::SUCCESS;
        }
        if ($process->getExitCode() === self::BUSY) {
            $this->line('Another deploy is running; leaving it to finish.');

            return self::SUCCESS;
        }

        // Reported by the next run, not this one: this process loaded its
        // classes before Composer may have replaced vendor/.
        $this->write(['commit' => $head, 'exit' => $process->getExitCode(), 'reported' => false]);
        $this->error('deploy.sh failed; see storage/logs/auto-deploy.log.');

        return self::FAILURE;
    }

    private function reportFailure(array $failed): void
    {
        $this->write([...$failed, 'reported' => true]);

        $log = storage_path('logs/auto-deploy.log');
        $lines = is_file($log) ? explode("\n", trim((string) file_get_contents($log))) : [];
        $tail = implode("\n", array_slice($lines, -15));

        report(new RuntimeException(
            'Automatic deploy of '.substr($failed['commit'], 0, 7).' failed (exit '.($failed['exit'] ?? '?').'). '
            ."It will not be retried for this commit; run 'bash scripts/deploy.sh' by hand once fixed. "
            ."Full output: storage/logs/auto-deploy.log. Last lines:\n".$tail
        ));
    }

    /** Whether the code moved back from what was deployed (deployed is not its ancestor). */
    private function wentBack(string $deployed): bool
    {
        $git = new Process(['git', 'merge-base', '--is-ancestor', $deployed, 'HEAD'], base_path());
        $git->run();

        // 0: forward. 1: not an ancestor. Anything else (an unknown commit,
        // say on a shallow copy): cannot tell, so deploy as before.
        return $git->getExitCode() === 1;
    }

    private function git(string ...$args): ?string
    {
        $git = new Process(['git', ...$args], base_path());
        $git->run();

        return $git->isSuccessful() ? trim($git->getOutput()) : null;
    }

    private function failed(): ?array
    {
        $raw = $this->read(self::FAILED);
        $data = $raw === null ? null : json_decode($raw, true);

        return is_array($data) && isset($data['commit']) ? $data : null;
    }

    private function write(array $failed): void
    {
        File::ensureDirectoryExists(dirname(storage_path(self::FAILED)));
        File::put(storage_path(self::FAILED), json_encode($failed));
    }

    private function read(string $file): ?string
    {
        $path = storage_path($file);

        return is_file($path) ? (trim((string) file_get_contents($path)) ?: null) : null;
    }
}
