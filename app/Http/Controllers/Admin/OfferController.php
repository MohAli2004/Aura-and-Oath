<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\BulkDestroysResources;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OfferRequest;
use App\Models\Offer;
use App\Services\AuditService;
use App\Services\OfferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OfferController extends Controller
{
    use BulkDestroysResources;

    public function __construct(
        protected OfferService $offers,
        protected AuditService $audit
    ) {}

    public function index(): View
    {
        return view('admin.offers.index', [
            'offers' => Offer::query()->withCount('products')->orderBy('sort_order')->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.offers.form', [
            'offer' => new Offer(['is_active' => true, 'sort_order' => 0]),
            'catalog' => $this->offers->catalogProducts(),
            'selected' => collect(),
        ]);
    }

    public function store(OfferRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('products');
        $data['slug'] = filled($data['slug'] ?? null)
            ? $data['slug']
            : Str::slug($data['title']).'-'.Str::lower(Str::random(4));

        $offer = Offer::query()->create($data);
        $this->offers->syncProducts($offer, $request->input('products', []));
        $this->audit->log('offer.created', $offer);

        return redirect()->route('admin.offers.index')->with('success', 'Offer created.');
    }

    public function edit(Offer $offer): View
    {
        $offer->load(['products' => fn ($query) => $query->orderByPivot('sort_order')]);

        return view('admin.offers.form', [
            'offer' => $offer,
            'catalog' => $this->offers->catalogProducts(),
            'selected' => $offer->products,
        ]);
    }

    public function update(OfferRequest $request, Offer $offer): RedirectResponse
    {
        $data = $request->safe()->except('products');
        $offer->update($data);
        $this->offers->syncProducts($offer, $request->input('products', []));
        $this->audit->log('offer.updated', $offer);

        return redirect()->route('admin.offers.index')->with('success', 'Offer updated.');
    }

    public function destroy(Offer $offer): RedirectResponse
    {
        $this->audit->log('offer.deleted', $offer);
        $offer->delete();
        $this->offers->forgetCache();

        return redirect()->route('admin.offers.index')->with('success', 'Offer deleted.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $response = $this->bulkDestroyModels(
            $request,
            Offer::class,
            'offers',
            'admin.offers.index',
            'offer',
            function (Offer $offer) {
                $this->audit->log('offer.deleted', $offer);
            },
        );

        $this->offers->forgetCache();

        return $response;
    }
}
