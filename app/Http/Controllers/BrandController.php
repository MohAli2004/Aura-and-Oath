<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(): View
    {
        return view('storefront.brands', [
            'brands' => Brand::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
