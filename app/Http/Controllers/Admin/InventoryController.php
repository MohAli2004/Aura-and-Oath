<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InventoryMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InventoryAdjustRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\BarcodeService;
use App\Services\InventoryService;
use App\Support\InventoryLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventory,
        protected BarcodeService $barcodes
    ) {}

    public function index(Request $request): View
    {
        $products = Product::query()
            ->with('variants')
            ->when($request->q, function ($q, $term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->paginate(25);

        $movements = InventoryMovement::query()->with(['product', 'variant', 'user'])->latest()->take(20)->get();
        $unlocked = InventoryLock::isUnlocked();
        $unlockedUntil = InventoryLock::unlockedUntil();

        return view('admin.inventory.index', compact('products', 'movements', 'unlocked', 'unlockedUntil'));
    }

    public function unlock(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = Auth::user();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Incorrect password. Inventory remains locked.',
            ]);
        }

        InventoryLock::unlock();

        return redirect()
            ->route('admin.inventory.index')
            ->with('success', 'Inventory unlocked for '.InventoryLock::TTL_MINUTES.' minutes.');
    }

    public function lock(): RedirectResponse
    {
        InventoryLock::lock();

        return redirect()
            ->route('admin.inventory.index')
            ->with('success', 'Inventory editing locked.');
    }

    public function adjust(InventoryAdjustRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $product = Product::query()->findOrFail($data['product_id']);
        $variant = ! empty($data['product_variant_id'])
            ? ProductVariant::query()->findOrFail($data['product_variant_id'])
            : null;

        $change = (int) $data['quantity_change'];
        $type = $change > 0 ? InventoryMovementType::AdjustmentAdd : InventoryMovementType::AdjustmentReduce;

        $this->inventory->adjust($product, $change, $type, $variant, Auth::user(), $data['notes'] ?? null);

        return back()->with('success', 'Inventory adjusted.');
    }

    public function scan(Request $request): RedirectResponse|View
    {
        $request->validate(['barcode' => ['required', 'string']]);
        $result = $this->barcodes->lookup($request->string('barcode')->toString());

        if (! $result) {
            return back()->with('error', 'Barcode not found.');
        }

        return view('admin.inventory.scan-result', [
            'result' => $result,
            'barcode' => $request->barcode,
            'unlocked' => InventoryLock::isUnlocked(),
        ]);
    }
}
