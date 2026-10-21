<?php

namespace App\Console\Commands;

use App\Support\PublicSite\SearchFiles;
use Illuminate\Console\Command;

/**
 * Writes public/robots.txt and public/sitemap.xml as real files, which is
 * what the server actually serves for those two addresses. Run by the
 * deploy, so adding a guide is enough to get it into the sitemap.
 *
 * Running it twice changes nothing more.
 */
class WriteSearchFiles extends Command
{
    protected $signature = 'site:search-files';

    protected $description = 'Write robots.txt and sitemap.xml into public/';

    public function handle(SearchFiles $files): int
    {
        // Nothing is asking for these files when they are written, so the
        // addresses in them come from APP_URL. Left at its default they
        // would all say localhost, and a sitemap of localhost addresses is
        // worse than no sitemap at all. Check the address itself rather
        // than the setting, because that is what ends up in the file.
        $home = route('home');

        if ($files->indexing() && str_contains($home, 'localhost')) {
            $this->error("The addresses would come out as {$home}. Set APP_URL to the site's own address and run this again.");

            return self::FAILURE;
        }

        foreach ($files->write() as $line) {
            $this->info($line);
        }

        return self::SUCCESS;
    }
}
