<?php

namespace App\Http\Controllers;

use App\Services\OfferService;
use App\Services\ProductSearchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request, ProductSearchService $search, OfferService $offers): View
    {
        $filters = $request->all();
        $filters['q'] = $request->get('q', '');

        return view('storefront.search', [
            'products' => $search->search($filters),
            'offers' => rescue(fn () => $offers->searchLive((string) $filters['q']), collect()),
            'q' => $filters['q'],
        ]);
    }
}
