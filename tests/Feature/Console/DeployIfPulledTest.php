<?php

namespace Tests\Feature\Console;

use App\Console\Commands\DeployIfPulled;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The host pulls new code on a merge without running deploy.sh, which left
 * the site on a stale route cache (November 2026). The scheduler runs
 * mybooks:deploy-if-pulled every minute: in production, when the code's
 * commit is not the one deploy.sh last finished, it runs deploy.sh. A
 * stand-in script plays deploy.sh here.
 */
class DeployIfPulledTest extends TestCase
{
    private string $dir;

    private string $head;

    protected function setUp(): void
    {
        parent::setUp();

        $head = $this->git('rev-parse', 'HEAD');
        if ($head === null) {
            $this->markTestSkipped('Needs a git checkout.');
        }
        $this->head = $head;

        $this->dir = storage_path('framework/testing/deploy-'.uniqid());
        File::ensureDirectoryExists($this->dir);
        File::delete([storage_path(DeployIfPulled::DEPLOYED), storage_path(DeployIfPulled::FAILED)]);
        app()->detectEnvironment(fn () => 'production');
        // CI checks out a detached commit, so the branch is whatever this is.
        config(['mybooks.auto_deploy.enabled' => true, 'mybooks.auto_deploy.branch' => $this->git('rev-parse', '--abbrev-ref', 'HEAD')]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        File::delete([storage_path(DeployIfPulled::DEPLOYED), storage_path(DeployIfPulled::FAILED)]);
        parent::tearDown();
    }

    private function git(string ...$args): ?string
    {
        $git = new Process(['git', ...$args], base_path(), [
            'GIT_AUTHOR_NAME' => 'Test', 'GIT_AUTHOR_EMAIL' => 'test@example.test',
            'GIT_COMMITTER_NAME' => 'Test', 'GIT_COMMITTER_EMAIL' => 'test@example.test',
        ]);
        $git->run();

        return $git->isSuccessful() ? trim($git->getOutput()) : null;
    }

    /** A stand-in deploy.sh: records its run and env, then exits with $exit (writing deployed-commit on success, as deploy.sh does). */
    private function script(int $exit = 0): string
    {
        $path = $this->dir.'/deploy.sh';
        $deployed = storage_path(DeployIfPulled::DEPLOYED);
        File::put($path, "#!/usr/bin/env bash\necho \"ran php=\$PHP no_pull=\${DEPLOY_NO_PULL:-0}\" >> {$this->dir}/runs\n"
            .($exit === 0 ? "git rev-parse HEAD > {$deployed}\n" : "echo 'migration failed' >&2\n")
            ."exit {$exit}\n");
        config(['mybooks.auto_deploy.script' => $path]);

        return $path;
    }

    private function runs(): array
    {
        return is_file($this->dir.'/runs') ? array_values(array_filter(explode("\n", File::get($this->dir.'/runs')))) : [];
    }

    public function test_new_code_is_deployed_as_it_is_without_a_pull(): void
    {
        $this->script();
        File::put(storage_path(DeployIfPulled::DEPLOYED), str_repeat('a', 40));

        $this->artisan('mybooks:deploy-if-pulled')->assertSuccessful();

        $this->assertCount(1, $this->runs());
        $this->assertStringContainsString('php='.PHP_BINARY, $this->runs()[0]);
        $this->assertStringContainsString('no_pull=1', $this->runs()[0]);
        $this->assertSame($this->head, trim(File::get(storage_path(DeployIfPulled::DEPLOYED))));
        $this->assertStringContainsString('deploying '.$this->head, File::get(storage_path('logs/auto-deploy.log')));
    }

    public function test_code_already_deployed_is_left_alone(): void
    {
        $this->script();
        File::put(storage_path(DeployIfPulled::DEPLOYED), $this->head);

        $this->artisan('mybooks:deploy-if-pulled')->assertSuccessful();

        $this->assertSame([], $this->runs());
    }

    public function test_code_rolled_back_is_not_moved_forward_again(): void
    {
        $this->script();
        // What was live is newer than the code: someone went back to an
        // older commit (docs/ROLLBACK.md). An unreferenced child commit
        // stands in for it.
        $newer = $this->git('commit-tree', 'HEAD^{tree}', '-p', 'HEAD', '-m', 'newer');
        $this->assertNotNull($newer);
        File::put(storage_path(DeployIfPulled::DEPLOYED), $newer);

        $this->artisan('mybooks:deploy-if-pulled')->assertSuccessful();

        $this->assertSame([], $this->runs());
    }

    public function test_a_failed_deploy_is_reported_once_by_the_next_run_and_not_retried(): void
    {
        Exceptions::fake();
        $this->script(1);
        File::put(storage_path(DeployIfPulled::DEPLOYED), str_repeat('a', 40));

        $this->artisan('mybooks:deploy-if-pulled')->assertFailed();
        $this->assertSame($this->head, json_decode(File::get(storage_path(DeployIfPulled::FAILED)), true)['commit']);
        // Not by the process that ran deploy.sh: vendor/ may have changed under it.
        Exceptions::assertNothingReported();

        // The next minute: reported, and the same commit is not tried again.
        $this->artisan('mybooks:deploy-if-pulled')->assertSuccessful();
        Exceptions::assertReported(fn (RuntimeException $e) => str_contains($e->getMessage(), 'Automatic deploy of '.substr($this->head, 0, 7).' failed (exit 1)')
            && str_contains($e->getMessage(), 'migration failed'));

        // And the minute after: nothing new.
        $this->artisan('mybooks:deploy-if-pulled')->assertSuccessful();
        Exceptions::assertReportedCount(1);
        $this->assertCount(1, $this->runs());
    }

    public function test_a_deploy_already_running_is_left_to_finish(): void
    {
        $this->script(DeployIfPulled::BUSY);
        File::put(storage_path(DeployIfPulled::DEPLOYED), str_repeat('a', 40));

        $this->artisan('mybooks:deploy-if-pulled')->assertSuccessful();

        $this->assertFileDoesNotExist(storage_path(DeployIfPulled::FAILED), 'Not a failure: tried again next minute.');
    }

    public function test_it_does_nothing_outside_production_on_another_branch_or_when_switched_off(): void
    {
        $this->script();

        app()->detectEnvironment(fn () => 'staging');
        $this->artisan('mybooks:deploy-if-pulled')->assertSuccessful();

        app()->detectEnvironment(fn () => 'production');
        config(['mybooks.auto_deploy.branch' => 'some-other-branch']);
        $this->artisan('mybooks:deploy-if-pulled')->assertSuccessful();

        config(['mybooks.auto_deploy.branch' => $this->git('rev-parse', '--abbrev-ref', 'HEAD'), 'mybooks.auto_deploy.enabled' => false]);
        $this->artisan('mybooks:deploy-if-pulled')->assertSuccessful();

        $this->assertSame([], $this->runs());
    }

    public function test_it_is_scheduled_every_minute_in_the_background_on_one_server(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'mybooks:deploy-if-pulled'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->runInBackground);
        $this->assertTrue($event->onOneServer);
    }

    public function test_deploy_sh_locks_records_the_commit_it_deployed_and_cannot_leave_the_site_down(): void
    {
        $script = File::get(base_path('scripts/deploy.sh'));

        $this->assertStringContainsString('flock -n 9', $script);
        $this->assertStringContainsString('exit '.DeployIfPulled::BUSY, $script);
        $this->assertStringContainsString('PREVIOUS_COMMIT="${DEPLOYED_FROM:-$(cat storage/app/deployed-commit', $script);
        $this->assertStringContainsString('if [ "${DEPLOY_NO_PULL:-}" = "1" ]', $script);
        // The commit read once, after the pull, is what is recorded: not
        // whatever the host pulled while the deploy ran.
        $this->assertStringContainsString('DEPLOYING="$(git rev-parse HEAD)"', $script);
        $this->assertStringContainsString('printf \'%s\n\' "$DEPLOYING" > storage/app/deployed-commit', $script);
        $this->assertSame(1, substr_count($script, 'git rev-parse HEAD)"'), 'Only DEPLOYING reads the code\'s commit after the pull.');
        // Recorded after migrations and before the site comes up.
        $recorded = strrpos($script, '> storage/app/deployed-commit');
        $this->assertGreaterThan(strrpos($script, '"$PHP" artisan migrate --force'), $recorded);
        $this->assertLessThan(strrpos($script, "\n\"\$PHP\" artisan up\n"), $recorded);
        $this->assertStringContainsString('|| rm -f storage/framework/down', $script);
    }

    public function test_deploy_sh_is_valid_bash(): void
    {
        $bash = new Process(['bash', '-n', base_path('scripts/deploy.sh')]);
        $bash->run();

        $this->assertTrue($bash->isSuccessful(), $bash->getErrorOutput());
    }
}
