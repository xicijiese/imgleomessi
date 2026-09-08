<?php

namespace App\Http\Controllers;

use App\Services\PublicOpponentArchive;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpponentArchiveController extends Controller
{
    public function index(Request $request, PublicOpponentArchive $archive): Response
    {
        return Inertia::render('Opponents/Index', [
            'archive' => $archive->indexPayload($request),
        ]);
    }

    public function show(Request $request, PublicOpponentArchive $archive, string $slug): Response
    {
        return Inertia::render('Opponents/Show', [
            'archive' => $archive->showPayload($request, $slug),
        ]);
    }
}