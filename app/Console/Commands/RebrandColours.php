<?php

namespace App\Console\Commands;

use App\Support\Rebrand\ColourRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Rebrand R1: swap old colours (indigo, blue, purple...) for the MyBooks
 * navy across a set of files, and list the lines a person must decide.
 *
 *   php artisan rebrand:colours resources/views/invoices            report only
 *   php artisan rebrand:colours resources/views/invoices --apply    make the swaps
 *
 * The rules are in App\Support\Rebrand\ColourRules and docs/REBRAND-PLAN.md.
 */
class RebrandColours extends Command
{
    protected $signature = 'rebrand:colours
        {paths* : Folders or files, relative to the project root}
        {--apply : Write the automatic swaps (without it nothing is changed)}
        {--summary : Only print one line per file}';

    protected $description = 'Swap old colours for the MyBooks brand colours and list lines that need a decision';

    public function handle(): int
    {
        $files = $this->files((array) $this->argument('paths'));
        if ($files === null) {
            return self::FAILURE;
        }

        $totalChanges = 0;
        $totalFlags = 0;
        $touched = 0;

        foreach ($files as $path) {
            $before = File::get($path);
            $result = ColourRules::apply($before);
            if ($result['changes'] === 0 && $result['flags'] === []) {
                continue;
            }
            $touched++;
            $totalChanges += $result['changes'];
            $totalFlags += count($result['flags']);

            $rel = ltrim(str_replace(base_path(), '', $path), '/');
            $this->line(sprintf('<info>%s</info>  %d swap(s), %d to decide', $rel, $result['changes'], count($result['flags'])));
            if (! $this->option('summary')) {
                foreach ($result['flags'] as $flag) {
                    $this->line(sprintf('    line %d: %s', $flag['line'], $flag['reason']));
                    $this->line('      <comment>'.mb_strimwidth($flag['text'], 0, 160, '...').'</comment>');
                }
            }

            if ($this->option('apply') && $result['text'] !== $before) {
                File::put($path, $result['text']);
            }
        }

        $this->newLine();
        $this->line(sprintf(
            '%d file(s) to change: %d swap(s) %s, %d line(s) to decide by hand.',
            $touched,
            $totalChanges,
            $this->option('apply') ? 'made' : 'ready (run again with --apply)',
            $totalFlags
        ));

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>|null
     */
    private function files(array $paths): ?array
    {
        $out = [];
        foreach ($paths as $p) {
            $full = base_path($p);
            // Stay inside the project.
            $real = realpath($full);
            if ($real === false || ! str_starts_with($real, realpath(base_path()) ?: base_path())) {
                $this->error("Not found inside the project: {$p}");

                return null;
            }
            if (is_dir($real)) {
                foreach (File::allFiles($real) as $f) {
                    if (preg_match('/\.(php|css|js|json|html|svg)$/', $f->getFilename())) {
                        $out[] = $f->getPathname();
                    }
                }
            } else {
                $out[] = $real;
            }
        }
        sort($out);

        return array_values(array_unique($out));
    }
}
