<?php

namespace App\Http\Controllers;

use App\Services\PublicSponsorship;
use Inertia\Inertia;
use Inertia\Response;

class SupporterWallController extends Controller
{
    public function __invoke(PublicSponsorship $sponsorship): Response
    {
        return Inertia::render('Supporters/Index', [
            'supportersPage' => $sponsorship->supporterWall(),
        ]);
    }
}
