<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\DeliveryRegion;
use App\Models\Offer;
use App\Models\Product;
use App\Services\ImageService;
use App\Services\SettingsService;
use App\Support\SiteOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(
        protected SettingsService $settings,
        protected ImageService $images,
    ) {}

    public function edit(): View
    {
        $flags = [];
        foreach (array_keys(SiteOptions::flags()) as $key) {
            $flags[$key] = old('flags.'.$key, SiteOptions::flag($key));
        }

        $fields = [];
        foreach (array_keys(SiteOptions::fields()) as $key) {
            $fields[$key] = old('fields.'.$key, SiteOptions::fieldValue($key));
        }

        return view('admin.settings.edit', [
            'flags' => $flags,
            'fields' => $fields,
            'deliveryRegions' => DeliveryRegion::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'catalogLinks' => $this->catalogLinks(),
            'logoPath' => $this->settings->get('logo_path'),
            'faviconPath' => $this->settings->get('favicon_path'),
            'homeBackgroundPath' => $this->settings->get('home_background_path'),
            'logoUrl' => store_logo_url(),
            'faviconUrl' => store_favicon_url(),
            'homeBackgroundUrl' => store_home_background_url(),
            'invoiceFields' => print_fields('invoice'),
            'packingSlipFields' => print_fields('packing_slip'),
            'invoiceSize' => print_page_size('invoice'),
            'packingSlipSize' => print_page_size('packing_slip'),
            'printSizes' => array_keys(config('aura.print.sizes', [])),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $invoiceKeys = array_keys(config('aura.print.invoice', []));
        $packingKeys = array_keys(config('aura.print.packing_slip', []));
        $sizeKeys = array_keys(config('aura.print.sizes', []));
        $flagKeys = array_keys(SiteOptions::flags());
        $fieldKeys = array_keys(SiteOptions::fields());

        $data = $request->validate([
            'flags' => ['nullable', 'array'],
            'flags.*' => ['nullable'],
            'fields' => ['nullable', 'array'],
            'fields.*' => ['nullable', 'string'],
            'delivery_regions' => ['nullable', 'array'],
            'delivery_regions.*' => ['nullable'],
            'invoice_fields' => ['nullable', 'array'],
            'invoice_fields.*' => ['string', 'in:'.implode(',', $invoiceKeys)],
            'packing_slip_fields' => ['nullable', 'array'],
            'packing_slip_fields.*' => ['string', 'in:'.implode(',', $packingKeys)],
            'invoice_size' => ['required', 'string', 'in:'.implode(',', $sizeKeys)],
            'packing_slip_size' => ['required', 'string', 'in:'.implode(',', $sizeKeys)],
            // SVG is excluded everywhere: it is a script-capable document and
            // these files are served from the public disk.
            'logo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
            'favicon' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,ico', 'mimetypes:image/jpeg,image/png,image/webp,image/vnd.microsoft.icon,image/x-icon', 'max:2048'],
            'home_background' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
            'remove_home_background' => ['nullable', 'boolean'],
        ]);

        $codEnabled = $request->boolean('flags.payment_cod_enabled');
        $wishEnabled = $request->boolean('flags.payment_wish_enabled');

        if (! $codEnabled && ! $wishEnabled) {
            return back()
                ->withInput()
                ->withErrors(['flags.payment_cod_enabled' => 'Keep at least one payment method visible to customers.']);
        }

        $postedRegions = is_array($data['delivery_regions'] ?? null) ? $data['delivery_regions'] : null;
        $regions = $postedRegions === null
            ? collect()
            : DeliveryRegion::query()->orderBy('id')->get();

        if ($postedRegions !== null && $regions->isNotEmpty()) {
            $anyVisible = $regions->contains(function (DeliveryRegion $region) use ($postedRegions) {
                $value = $postedRegions[$region->id] ?? $postedRegions[(string) $region->id] ?? null;
                if ($value !== null || array_key_exists($region->id, $postedRegions) || array_key_exists((string) $region->id, $postedRegions)) {
                    return filter_var($postedRegions[$region->id] ?? $postedRegions[(string) $region->id] ?? 0, FILTER_VALIDATE_BOOLEAN);
                }

                return $region->is_active;
            });

            if (! $anyVisible) {
                return back()
                    ->withInput()
                    ->withErrors(['delivery_regions' => 'Keep at least one delivery choice visible to customers.']);
            }
        }

        foreach ($flagKeys as $key) {
            $meta = SiteOptions::flagMeta()[$key];
            $this->settings->set(
                $key,
                $request->boolean('flags.'.$key),
                $meta['type'],
                $meta['group'],
                $meta['public'],
            );
        }

        foreach ($fieldKeys as $key) {
            $meta = SiteOptions::fields()[$key];
            $this->settings->set(
                $key,
                $data['fields'][$key] ?? '',
                $meta['type'],
                $meta['group'],
                $meta['public'],
            );
        }

        if ($postedRegions !== null) {
            foreach ($regions as $region) {
                $value = $postedRegions[$region->id] ?? $postedRegions[(string) $region->id] ?? null;
                if ($value === null && ! array_key_exists($region->id, $postedRegions) && ! array_key_exists((string) $region->id, $postedRegions)) {
                    continue;
                }

                $region->update([
                    'is_active' => filter_var($postedRegions[$region->id] ?? $postedRegions[(string) $region->id] ?? 0, FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }

        $this->settings->set(
            'invoice_fields',
            array_values(array_intersect($invoiceKeys, $data['invoice_fields'] ?? [])),
            'json',
            'print',
            false,
        );

        $this->settings->set(
            'packing_slip_fields',
            array_values(array_intersect($packingKeys, $data['packing_slip_fields'] ?? [])),
            'json',
            'print',
            false,
        );

        $this->settings->set('invoice_size', $data['invoice_size'], 'string', 'print', false);
        $this->settings->set('packing_slip_size', $data['packing_slip_size'], 'string', 'print', false);

        if ($request->boolean('remove_logo')) {
            $this->images->delete($this->settings->get('logo_path'));
            $this->settings->set('logo_path', '', 'string', 'general', true);
        }

        if ($request->file('logo')) {
            $this->images->delete($this->settings->get('logo_path'));
            $path = $this->images->store($request->file('logo'), 'branding');
            $this->settings->set('logo_path', $path, 'string', 'general', true);
        }

        if ($request->boolean('remove_favicon')) {
            $this->images->delete($this->settings->get('favicon_path'));
            $this->settings->set('favicon_path', '', 'string', 'general', true);
        }

        if ($request->file('favicon')) {
            $this->images->delete($this->settings->get('favicon_path'));
            $path = $this->images->store($request->file('favicon'), 'branding');
            $this->settings->set('favicon_path', $path, 'string', 'general', true);
        }

        if ($request->boolean('remove_home_background')) {
            $this->images->delete($this->settings->get('home_background_path'));
            $this->settings->set('home_background_path', '', 'string', 'general', true);
        }

        if ($request->file('home_background')) {
            $this->images->delete($this->settings->get('home_background_path'));
            $path = $this->images->store($request->file('home_background'), 'branding');
            $this->settings->set('home_background_path', $path, 'string', 'general', true);
        }

        return back()->with('success', 'Control panel saved.');
    }

    /**
     * @return list<array{label: string, hint: string, route: string, status: string}>
     */
    protected function catalogLinks(): array
    {
        $activeProducts = Product::query()->where('status', ProductStatus::Active)->count();
        $totalProducts = Product::query()->count();
        $activeBanners = Banner::query()->where('is_active', true)->count();
        $activeOffers = Offer::query()->where('is_active', true)->count();
        $activeCategories = Category::query()->where('is_active', true)->count();
        $activeBrands = Brand::query()->where('is_active', true)->count();
        $activeCoupons = Coupon::query()->where('is_active', true)->count();
        $activeRegions = DeliveryRegion::query()->where('is_active', true)->count();

        return [
            [
                'label' => 'Banners',
                'hint' => $activeBanners.' active of '.Banner::query()->count(),
                'route' => 'admin.banners.index',
                'status' => 'Hero slides and homepage banners',
            ],
            [
                'label' => 'Hot offers',
                'hint' => $activeOffers.' active of '.Offer::query()->count(),
                'route' => 'admin.offers.index',
                'status' => 'Offer groups shown on the storefront',
            ],
            [
                'label' => 'Categories',
                'hint' => $activeCategories.' visible of '.Category::query()->count(),
                'route' => 'admin.categories.index',
                'status' => 'Shop navigation and homepage tiles',
            ],
            [
                'label' => 'Brands',
                'hint' => $activeBrands.' visible of '.Brand::query()->count(),
                'route' => 'admin.brands.index',
                'status' => 'Brand menu and filters',
            ],
            [
                'label' => 'Coupons',
                'hint' => $activeCoupons.' active of '.Coupon::query()->count(),
                'route' => 'admin.coupons.index',
                'status' => 'Checkout discount codes',
            ],
            [
                'label' => 'Delivery regions',
                'hint' => $activeRegions.' visible of '.DeliveryRegion::query()->count(),
                'route' => 'admin.delivery-regions.index',
                'status' => 'Add or edit regions, fees, and coverage',
            ],
            [
                'label' => 'Products',
                'hint' => $activeProducts.' active of '.$totalProducts,
                'route' => 'admin.products.index',
                'status' => 'Catalog visibility and stock',
            ],
        ];
    }
}
