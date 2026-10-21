<?php

namespace App\Http\Controllers;

use App\Support\PublicSite\FeaturePages;
use Illuminate\View\View;

/**
 * One page per thing MyBooks does (SEO plan). Public, outside the
 * signed-in app and outside tenancy.
 */
class FeatureController extends Controller
{
    public function __construct(private readonly FeaturePages $pages) {}

    public function show(string $slug): View
    {
        $page = $this->pages->find($slug);
        abort_if($page === null, 404);

        return view($page['view'], ['page' => $page]);
    }
}
