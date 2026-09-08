<?php

namespace App\Http\Controllers;

use App\Services\PublicSponsorship;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportResultController extends Controller
{
    public function __invoke(Request $request, PublicSponsorship $sponsorship): Response
    {
        return Inertia::render('Support/Result', [
            'supportResult' => $sponsorship->resultPage($request->user(), $request->string('order')->toString()),
        ]);
    }
}
