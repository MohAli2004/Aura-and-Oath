<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\BulkDestroysResources;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeliveryRegionRequest;
use App\Models\DeliveryRegion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryRegionController extends Controller
{
    use BulkDestroysResources;

    public function index(): View
    {
        return view('admin.delivery-regions.index', [
            'regions' => DeliveryRegion::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.delivery-regions.form', [
            'region' => new DeliveryRegion([
                'fee' => 0,
                'estimated_days_min' => 1,
                'estimated_days_max' => 5,
                'is_active' => true,
                'sort_order' => 0,
            ]),
        ]);
    }

    public function store(DeliveryRegionRequest $request): RedirectResponse
    {
        DeliveryRegion::query()->create($request->validated());

        return redirect()->route('admin.delivery-regions.index')->with('success', 'Delivery region created.');
    }

    public function edit(DeliveryRegion $deliveryRegion): View
    {
        return view('admin.delivery-regions.form', [
            'region' => $deliveryRegion,
        ]);
    }

    public function update(DeliveryRegionRequest $request, DeliveryRegion $deliveryRegion): RedirectResponse
    {
        $data = $request->validated();
        if (! ($data['is_active'] ?? false) && ! $this->anotherRegionIsVisible($deliveryRegion)) {
            return back()
                ->withInput()
                ->withErrors(['is_active' => 'Keep at least one delivery choice visible to customers.']);
        }

        $deliveryRegion->update($data);

        return redirect()->route('admin.delivery-regions.index')->with('success', 'Delivery region updated.');
    }

    public function updateVisibility(Request $request, DeliveryRegion $deliveryRegion): RedirectResponse
    {
        $visible = $request->boolean('is_active');
        if (! $visible && ! $this->anotherRegionIsVisible($deliveryRegion)) {
            return back()->with('error', 'Keep at least one delivery choice visible to customers.');
        }

        $deliveryRegion->update(['is_active' => $visible]);

        return back()->with('success', $visible
            ? $deliveryRegion->name.' is visible at checkout.'
            : $deliveryRegion->name.' is hidden from customers.');
    }

    public function destroy(DeliveryRegion $deliveryRegion): RedirectResponse
    {
        $deliveryRegion->delete();

        return redirect()->route('admin.delivery-regions.index')->with('success', 'Delivery region deleted.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        return $this->bulkDestroyModels(
            $request,
            DeliveryRegion::class,
            'delivery_regions',
            'admin.delivery-regions.index',
            'delivery region',
        );
    }

    protected function anotherRegionIsVisible(DeliveryRegion $region): bool
    {
        return DeliveryRegion::query()
            ->active()
            ->where('id', '!=', $region->id)
            ->exists();
    }
}
