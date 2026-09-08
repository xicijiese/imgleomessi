<?php

namespace App\Http\Controllers;

use App\Services\PublicPhotoDetail;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PhotoDetailController extends Controller
{
    public function __invoke(Request $request, string $uuid, PublicPhotoDetail $photos): Response
    {
        return Inertia::render('Photos/Show', [
            'photoDetail' => $photos->payload($request, $uuid),
        ]);
    }
}
