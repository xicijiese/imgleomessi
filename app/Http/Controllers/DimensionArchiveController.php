<?php

namespace App\Http\Controllers;

use App\Services\PublicDimensionArchive;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DimensionArchiveController extends Controller
{
    public function teams(Request $request, PublicDimensionArchive $archive): Response
    {
        return Inertia::render('Dimensions/Index', [
            'archive' => $archive->indexPayload($request, 'teams'),
        ]);
    }

    public function team(Request $request, PublicDimensionArchive $archive, string $slug): Response
    {
        return Inertia::render('Dimensions/Show', [
            'archive' => $archive->showPayload($request, 'teams', $slug),
        ]);
    }

    public function seasons(Request $request, PublicDimensionArchive $archive): Response
    {
        return Inertia::render('Dimensions/Index', [
            'archive' => $archive->indexPayload($request, 'seasons'),
        ]);
    }

    public function season(Request $request, PublicDimensionArchive $archive, string $slug): Response
    {
        return Inertia::render('Dimensions/Show', [
            'archive' => $archive->showPayload($request, 'seasons', $slug),
        ]);
    }
}