<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * What MyBooks costs, as its own page (SEO plan). People search for a
 * product's name plus "pricing", and a section of the home page cannot
 * be found on its own. The plans themselves come from the plans table,
 * through the shared pricing component.
 */
class PricingController extends Controller
{
    public function index(): View
    {
        return view('public-site.pricing');
    }
}
