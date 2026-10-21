<?php

namespace App\Http\Controllers;

use App\Support\PublicSite\Guides;
use Illuminate\View\View;

/**
 * The guides: plain-English articles about VAT, WHT and keeping books in
 * Nigeria (SEO plan). Public, outside the signed-in app and outside
 * tenancy — these pages touch no business's data.
 */
class GuideController extends Controller
{
    public function __construct(private readonly Guides $guides) {}

    public function index(): View
    {
        return view('public-site.guides.index', ['guides' => $this->guides->all()]);
    }

    public function show(string $slug): View
    {
        $guide = $this->guides->find($slug);
        abort_if($guide === null, 404);

        return view('public-site.guides.show', [
            'guide' => $guide,
            'more' => $this->guides->all()->reject(fn (array $other) => $other['slug'] === $slug)->take(3),
        ]);
    }
}
