<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request, SearchService $service): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['query' => $q, 'results' => []]);
        }

        return response()->json([
            'query' => $q,
            'results' => $service->search($request->user(), $q),
        ]);
    }
}
