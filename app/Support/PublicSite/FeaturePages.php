<?php

namespace App\Support\PublicSite;

use Illuminate\Support\Collection;

/**
 * The feature pages (SEO plan). One page per thing MyBooks does, because
 * a section of the home page cannot be found on its own, and someone
 * searching "VAT software Nigeria" is looking for that one thing.
 *
 * Listed here rather than discovered from the views folder, so the order
 * is deliberate and a half-written page cannot appear by accident. Each
 * slug has a view at public-site/features/{slug}.
 *
 * @phpstan-type FeaturePage array{slug: string, view: string, title: string, short: string, description: string}
 */
class FeaturePages
{
    /** @var array<string, array{title: string, short: string, description: string}> */
    private const PAGES = [
        'vat-returns' => [
            'title' => 'VAT returns for Nigeria',
            'short' => 'VAT returns',
            'description' => 'MyBooks adds 7.5% VAT to your invoices and bills, then lays out your monthly return as NRS Form 002, with the schedules to upload.',
        ],
        'invoicing' => [
            'title' => 'Invoicing for Nigerian businesses',
            'short' => 'Invoicing',
            'description' => 'Send invoices in naira with your own logo, record what customers pay, and see what is still owed. Works in your phone\'s browser.',
        ],
    ];

    /** @return Collection<int, FeaturePage> */
    public function all(): Collection
    {
        return collect(array_keys(self::PAGES))
            ->map(fn (string $slug) => $this->find($slug))
            ->filter()
            ->values();
    }

    /** @return FeaturePage|null */
    public function find(string $slug): ?array
    {
        $page = self::PAGES[$slug] ?? null;

        return $page === null ? null : [
            'slug' => $slug,
            // Named here rather than built from the slug, so every view a
            // route can render is a literal somebody can go and find.
            'view' => 'public-site.features.'.$slug,
            'title' => $page['title'],
            'short' => $page['short'],
            'description' => $page['description'],
        ];
    }
}
