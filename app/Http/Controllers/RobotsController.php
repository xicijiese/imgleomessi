<?php

namespace App\Http\Controllers;

use App\Services\PublicSeo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(Request $request, PublicSeo $seo): Response
    {
        return response($seo->robots($request), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
