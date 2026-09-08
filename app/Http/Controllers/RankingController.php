<?php

namespace App\Http\Controllers;

use App\Services\PhotoInteractionRankings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RankingController extends Controller
{
    public function __invoke(Request $request, PhotoInteractionRankings $rankings): Response
    {
        return Inertia::render('Rankings/Index', [
            'rankings' => $rankings->publicPayload($request->only(['type', 'window'])),
        ]);
    }
}
