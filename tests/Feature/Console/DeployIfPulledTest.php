<?php

namespace Tests\Feature\Console;

use App\Console\Commands\DeployIfPulled;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
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

        $git = new Process(['git', 'rev-parse', 'HEAD'], base_path());
        $git->run();
        if (! $git->isSuccessful()) {
            $this->markTestSkipped('Needs a git checkout.');
        }
        $this->head = trim($git->getOutput());

        $this->dir = storage_path('framework/testing/deploy-'.uniqid());
        File::ensureDirectoryExists($this->dir);
        File::delete([storage_path(DeployIfPulled::DEPLOYED), storage_path(DeployIfPulled::FAILED)]);
        app()->detectEnvironment(fn () => 'production');
        config(['mybooks.auto_deploy.enabled' => true]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        File::delete([storage_path(DeployIfPulled::DEPLOYED), storage_path(DeployIfPulled::FAILED)]);
        parent::tearDown();
    }

    /** A stand-in deploy.sh: records its run and env, then exits with $exit (writing deployed-commit on success, as deploy.sh does). */
    private function script(int $exit = 0): string
    {
        $path = $this->dir.'/deploy.sh';
        $deployed = storage_path(DeployIfPulled::DEPLOYED);
        File::put($path, "#!/usr/bin/env bash\necho \"ran from=\${DEPLOYED_FROM:-none} php=\$PHP\" >> {$this->dir}/runs\n"
            .($exit === 0 ? "git rev-parse HEAD > {$deployed}\n" : "echo 'migration failed' >&2\n")
            ."exit {$exit}\n");
        config(['mybooks.auto_deploy.script' => $path]);

        return $path;
    }

    private function runs(): array
    {
        return is_file($this->dir.'/runs') ? array_values(array_filter(explode("\n", File::get($this->dir.'/runs')))) : [];
    }

    public function test_new_code_is_deployed_and_names_what_was_live(): void
    {
        $this->script();
        File::put(storage_path(DeployIfPulled::DEPLOYED), str_repeat('a', 40));

        $this->artisan('mybooks:deploy-if-pulled')->assertSuccessful();

        $this->assertCount(1, $this->runs());
        $this->assertStringContainsString('from='.str_repeat('a', 40), $this->runs()[0]);
        $this->assertStringContainsString('php='.PHP_BINARY, $this->runs()[0]);
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

    public function test_a_failed_deploy_is_reported_once_and_not_retried_every_minute(): void
    {
        Mail::fake();
        config(['mybooks.error_alerts.enabled' => true, 'mybooks.error_alerts.email' => 'alerts@example.test']);
        $this->script(1);
        File::put(storage_path(DeployIfPulled::DEPLOYED), str_repeat('a', 40));

        $this->artisan('mybooks:deploy-if-pulled')->assertFailed();
        $this->assertSame($this->head, trim(File::get(storage_path(DeployIfPulled::FAILED))));

        // The next minute: the same commit is not tried again.
        $this->artisan('mybooks:deploy-if-pulled')->assertSuccessful();
        $this->assertCount(1, $this->runs());
    }

    public function test_it_does_nothing_outside_production_or_when_switched_off(): void
    {
        $this->script();

        app()->detectEnvironment(fn () => 'staging');
        $this->artisan('mybooks:deploy-if-pulled')->assertSuccessful();

        app()->detectEnvironment(fn () => 'production');
        config(['mybooks.auto_deploy.enabled' => false]);
        $this->artisan('mybooks:deploy-if-pulled')->assertSuccessful();

        $this->assertSame([], $this->runs());
    }

    public function test_it_is_scheduled_every_minute_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'mybooks:deploy-if-pulled'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_deploy_sh_records_what_was_live_and_what_it_deployed(): void
    {
        $script = File::get(base_path('scripts/deploy.sh'));

        $this->assertStringContainsString('PREVIOUS_COMMIT="${DEPLOYED_FROM:-$(git rev-parse HEAD)}"', $script);
        $this->assertStringContainsString('git rev-parse HEAD > storage/app/deployed-commit', $script);
        // Written after the site is back up, so a failed step leaves it unwritten.
        $this->assertGreaterThan(strrpos($script, '"$PHP" artisan migrate --force'), strrpos($script, 'deployed-commit'));
    }
}
