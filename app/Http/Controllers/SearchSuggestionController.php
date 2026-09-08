<?php

namespace App\Http\Controllers;

use App\Services\PublicSearchOperations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchSuggestionController extends Controller
{
    public function __invoke(Request $request, PublicSearchOperations $operations): JsonResponse
    {
        return response()->json($operations->suggestions($request));
    }
}
