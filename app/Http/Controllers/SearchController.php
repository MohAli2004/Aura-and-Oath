<?php

namespace App\Http\Controllers;

use App\Services\ProductSearchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request, ProductSearchService $search): View
    {
        $filters = $request->all();
        $filters['q'] = $request->get('q', '');

        return view('storefront.search', [
            'products' => $search->search($filters),
            'q' => $filters['q'],
        ]);
    }
}
