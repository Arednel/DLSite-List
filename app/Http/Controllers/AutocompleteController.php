<?php

namespace App\Http\Controllers;

use App\Models\Option;
use App\Support\Autocomplete\SeriesAutocompleteSearch;
use App\Support\Autocomplete\TagAutocompleteSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutocompleteController extends Controller
{
    public function __construct(
        private readonly TagAutocompleteSearch $tagSearch,
        private readonly SeriesAutocompleteSearch $seriesSearch,
    ) {}

    public function tags(Request $request): JsonResponse
    {
        $query = $this->normalizedQuery($request);

        if ($query === '') {
            return response()->json([]);
        }

        return response()->json(
            $this->tagSearch->search(
                $query,
                Option::tagAutocompleteOrder(),
                Option::tagColorSurfaceEnabled(Option::TAG_COLOR_SURFACE_AUTOCOMPLETE),
            )
        );
    }

    public function series(Request $request): JsonResponse
    {
        $query = $this->normalizedQuery($request);

        if ($query === '') {
            return response()->json([]);
        }

        return response()->json(
            $this->seriesSearch->search($query, Option::seriesAutocompleteOrder())
        );
    }

    private function normalizedQuery(Request $request): string
    {
        return trim((string) $request->query('q', ''));
    }
}
