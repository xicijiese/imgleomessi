<?php

namespace App\Http\Controllers;

use App\Services\PublicHomepage;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class HomeController extends Controller
{
    public function __invoke(PublicHomepage $homepage): Response
    {
        return Inertia::render('Welcome', [
            'canRegister' => Features::enabled(Features::registration()),
            'home' => $homepage->payload(),
        ]);
    }
}
