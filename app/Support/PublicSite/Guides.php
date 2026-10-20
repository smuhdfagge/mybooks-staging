<?php

namespace App\Support\PublicSite;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The guides: the plain-English articles that bring Nigerian businesses
 * to mybooks.cloud while they are still looking for an answer, not yet
 * for software (SEO plan).
 *
 * Each one is a markdown file in resources/guides, with a short header
 * block at the top. Files rather than a database table, so a guide is
 * reviewed and deployed like any other change, and so there is no editor
 * to build before the first article is written.
 *
 * @phpstan-type Guide array{
 *     slug: string, title: string, heading: string, description: string,
 *     published: string, updated: string, body: string
 * }
 */
class Guides
{
    /** Where the markdown files live, relative to resources/. */
    private const FOLDER = 'guides';

    /**
     * Every guide, newest first.
     *
     * @return Collection<int, Guide>
     */
    public function all(): Collection
    {
        $folder = resource_path(self::FOLDER);
        if (! File::isDirectory($folder)) {
            return collect();
        }

        return collect(File::files($folder))
            ->filter(fn ($file) => $file->getExtension() === 'md')
            ->map(fn ($file) => $this->parse($file->getFilenameWithoutExtension(), File::get($file->getPathname())))
            ->filter()
            ->sortByDesc('published')
            ->values();
    }

    /**
     * One guide by its address, or null when there is no such file.
     *
     * @return Guide|null
     */
    public function find(string $slug): ?array
    {
        // The slug comes from the address, so it may be anything.
        if (! preg_match('/^[a-z0-9-]{1,100}$/', $slug)) {
            return null;
        }

        $path = resource_path(self::FOLDER.'/'.$slug.'.md');

        return File::exists($path) ? $this->parse($slug, File::get($path)) : null;
    }

    /**
     * Split a file into its header and its body.
     *
     * The header is a few "key: value" lines between --- fences, which is
     * enough here and saves a package the server would have to install.
     *
     * @return Guide|null
     */
    private function parse(string $slug, string $contents): ?array
    {
        $contents = str_replace("\r\n", "\n", $contents);
        if (! preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $contents, $matches)) {
            return null;
        }

        $header = [];
        foreach (explode("\n", $matches[1]) as $line) {
            if (preg_match('/^([a-z_]+):\s*(.*)$/i', trim($line), $pair)) {
                $header[strtolower($pair[1])] = trim($pair[2], " \"'");
            }
        }

        $title = $header['title'] ?? Str::headline($slug);

        return [
            'slug' => $slug,
            // The browser tab and the search result.
            'title' => $title,
            // The heading on the page, which may carry the year.
            'heading' => $header['heading'] ?? $title,
            'description' => $header['description'] ?? '',
            'published' => $header['published'] ?? '',
            // A guide about tax says when it was last checked, and search
            // engines favour pages that are kept current.
            'updated' => $header['updated'] ?? ($header['published'] ?? ''),
            'body' => trim($matches[2]),
        ];
    }
}
