<?php

namespace App\Http\Controllers;

use App\Services\PublicSeo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(Request $request, PublicSeo $seo): Response
    {
        return response($seo->sitemap($request), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
