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
 * deploy.sh last finished, and runs deploy.sh when they differ: backup
 * first, then packages, migrations and caches. Its output goes to
 * storage/logs/auto-deploy.log. A commit whose deploy failed is not tried
 * again every minute: the failure is reported (the error alert email) and
 * the next new commit, or a deploy.sh run by hand, starts over.
 *
 * Production only, and switched off with MYBOOKS_AUTO_DEPLOY=false.
 */
class DeployIfPulled extends Command
{
    protected $signature = 'mybooks:deploy-if-pulled';

    protected $description = 'Run deploy.sh when the host has pulled code that has not been deployed';

    public const DEPLOYED = 'app/deployed-commit';

    public const FAILED = 'app/auto-deploy-failed';

    public function handle(): int
    {
        if (! config('mybooks.auto_deploy.enabled') || ! app()->environment('production')) {
            return self::SUCCESS;
        }

        $script = (string) config('mybooks.auto_deploy.script');
        if (! is_file($script) || ! is_dir(base_path('.git'))) {
            return self::SUCCESS;
        }

        $head = $this->head();
        if ($head === null) {
            $this->warn('Could not read the commit of the code in this folder.');

            return self::SUCCESS;
        }

        $deployed = $this->read(self::DEPLOYED);
        if ($deployed === $head) {
            return self::SUCCESS;
        }
        if ($this->read(self::FAILED) === $head) {
            return self::SUCCESS; // Reported already; waits for new code or a deploy by hand.
        }

        $this->info('New code '.substr($head, 0, 7).($deployed ? ' (live: '.substr($deployed, 0, 7).')' : '').': running deploy.sh.');

        $env = ['PHP' => PHP_BINARY, 'GIT_TERMINAL_PROMPT' => '0'];
        if ($deployed) {
            $env['DEPLOYED_FROM'] = $deployed; // So the rollback record names what was live.
        }
        if ($composer = config('mybooks.auto_deploy.composer')) {
            $env['COMPOSER_BIN'] = $composer;
        }

        $log = storage_path('logs/auto-deploy.log');
        File::ensureDirectoryExists(dirname($log));
        File::append($log, "\n=== ".now()->toIso8601String().' deploying '.$head."\n");

        $process = new Process(['bash', $script], base_path(), $env, null, (float) config('mybooks.auto_deploy.timeout', 900));
        $process->run(function ($type, $buffer) use ($log) {
            File::append($log, $buffer);
        });

        if ($process->isSuccessful()) {
            File::delete(storage_path(self::FAILED));
            $this->info('Deployed '.substr($head, 0, 7).'.');

            return self::SUCCESS;
        }

        File::put(storage_path(self::FAILED), $head);
        $tail = implode("\n", array_slice(explode("\n", trim($process->getOutput()."\n".$process->getErrorOutput())), -15));
        report(new RuntimeException(
            'Automatic deploy of '.substr($head, 0, 7).' failed (exit '.$process->getExitCode().'). '
            ."It will not be retried for this commit; run 'bash scripts/deploy.sh' by hand once fixed. "
            ."Full output: storage/logs/auto-deploy.log. Last lines:\n".$tail
        ));
        $this->error('deploy.sh failed; see storage/logs/auto-deploy.log.');

        return self::FAILURE;
    }

    private function head(): ?string
    {
        $git = new Process(['git', 'rev-parse', 'HEAD'], base_path());
        $git->run();
        $sha = trim($git->getOutput());

        return $git->isSuccessful() && preg_match('/^[0-9a-f]{40}$/', $sha) ? $sha : null;
    }

    private function read(string $file): ?string
    {
        $path = storage_path($file);

        return is_file($path) ? (trim((string) file_get_contents($path)) ?: null) : null;
    }
}
