<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Services\OfferService;
use Illuminate\View\View;

class OfferController extends Controller
{
    public function __construct(protected OfferService $offers) {}

    public function index(): View
    {
        return view('storefront.offers.index', [
            'offers' => $this->offers->liveOffers(24),
        ]);
    }

    public function show(string $slug): View
    {
        $offer = Offer::query()
            ->active()
            ->where('slug', $slug)
            ->with(['products' => function ($query) {
                $query->with(['images', 'brand', 'activeVariants'])
                    ->active()
                    ->published();
            }])
            ->firstOrFail();

        abort_if($offer->products->isEmpty(), 404);

        return view('storefront.offers.show', compact('offer'));
    }
}
