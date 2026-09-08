<?php

namespace App\Http\Controllers;

use App\Services\PublicSponsorship;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    public function __invoke(Request $request, PublicSponsorship $sponsorship): Response
    {
        return Inertia::render('Support/Index', [
            'support' => $sponsorship->supportPage($request->user()),
        ]);
    }
}
