<?php

namespace App\Http\Controllers;

use App\Services\PublicTimeline;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimelineController extends Controller
{
    public function index(Request $request, PublicTimeline $timeline): Response
    {
        return Inertia::render('Timeline/Index', [
            'timeline' => $timeline->indexPayload($request),
        ]);
    }

    public function year(Request $request, PublicTimeline $timeline, int $year): Response
    {
        return Inertia::render('Timeline/Archive', [
            'timeline' => $timeline->yearPayload($request, $year),
        ]);
    }

    public function month(Request $request, PublicTimeline $timeline, int $year, int $month): Response
    {
        return Inertia::render('Timeline/Archive', [
            'timeline' => $timeline->monthPayload($request, $year, $month),
        ]);
    }
}
