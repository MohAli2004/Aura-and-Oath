<?php

namespace Database\Seeders;

use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductGender;
use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Enums\StockStatus;
use App\Enums\UserRole;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\DeliveryRegion;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSettings();
        $admin = $this->seedUsers();
        $categories = $this->seedCategories();
        $brands = $this->seedBrands();
        $attributes = $this->seedAttributes();
        $products = $this->seedProducts($categories, $brands, $attributes);
        $this->seedDeliveryRegions();
        $this->seedCoupons();
        $this->seedBanners();
        $this->seedPages();
        $this->seedOrders($admin, $products);
    }

    protected function seedSettings(): void
    {
        $rows = [
            ['group' => 'general', 'key' => 'store_name', 'value' => 'Aura & Oath', 'type' => 'string', 'is_public' => true],
            ['group' => 'general', 'key' => 'support_email', 'value' => 'auraandouth@gmail.com', 'type' => 'string', 'is_public' => true],
            ['group' => 'general', 'key' => 'logo_path', 'value' => '', 'type' => 'string', 'is_public' => true],
            ['group' => 'general', 'key' => 'favicon_path', 'value' => '', 'type' => 'string', 'is_public' => true],
            ['group' => 'general', 'key' => 'home_background_path', 'value' => '', 'type' => 'string', 'is_public' => true],
            ['group' => 'store', 'key' => 'tax_rate', 'value' => '0', 'type' => 'decimal', 'is_public' => true],
            ['group' => 'store', 'key' => 'currency', 'value' => 'USD', 'type' => 'string', 'is_public' => true],
            ['group' => 'store', 'key' => 'currency_symbol', 'value' => '$', 'type' => 'string', 'is_public' => true],
            ['group' => 'shipping', 'key' => 'default_delivery_note', 'value' => 'Delivery within Lebanon in 1–3 business days, depending on distance.', 'type' => 'string', 'is_public' => true],
            [
                'group' => 'print',
                'key' => 'invoice_fields',
                'value' => json_encode(array_keys(config('aura.print.invoice', []))),
                'type' => 'json',
                'is_public' => false,
            ],
            [
                'group' => 'print',
                'key' => 'packing_slip_fields',
                'value' => json_encode(array_keys(config('aura.print.packing_slip', []))),
                'type' => 'json',
                'is_public' => false,
            ],
            [
                'group' => 'print',
                'key' => 'invoice_size',
                'value' => config('aura.print.defaults.invoice', 'A5'),
                'type' => 'string',
                'is_public' => false,
            ],
            [
                'group' => 'print',
                'key' => 'packing_slip_size',
                'value' => config('aura.print.defaults.packing_slip', 'A4'),
                'type' => 'string',
                'is_public' => false,
            ],
        ];

        foreach ($rows as $row) {
            Setting::query()->updateOrCreate(['key' => $row['key']], $row);
        }
    }

    protected function seedUsers(): User
    {
        $admin = User::query()->updateOrCreate(
            ['email' => config('aura.admin.email')],
            [
                'name' => config('aura.admin.name'),
                'password' => Hash::make(config('aura.admin.password')),
                'role' => UserRole::Admin,
                'phone' => '+96171000001',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $customers = [
            ['name' => 'Nour Hassan', 'email' => 'nour@example.com', 'phone' => '+96171111111'],
            ['name' => 'Sara Ali', 'email' => 'sara@example.com', 'phone' => '+96171222222'],
            ['name' => 'Omar Farid', 'email' => 'omar@example.com', 'phone' => '+96171333333'],
            ['name' => 'Lina Khalil', 'email' => 'lina@example.com', 'phone' => '+96171444444'],
            ['name' => 'Yasmine Haddad', 'email' => 'yasmine@example.com', 'phone' => '+96171555555'],
        ];

        foreach ($customers as $c) {
            $user = User::query()->updateOrCreate(
                ['email' => $c['email']],
                [
                    'name' => $c['name'],
                    'phone' => $c['phone'],
                    'password' => Hash::make('password'),
                    'role' => UserRole::Customer,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            CustomerAddress::query()->updateOrCreate(
                ['user_id' => $user->id, 'label' => 'Home'],
                [
                    'type' => 'shipping',
                    'full_name' => $user->name,
                    'phone' => $user->phone,
                    'line1' => '12 Hamra Street',
                    'city' => 'Beirut',
                    'governorate' => 'Beirut',
                    'country' => 'LB',
                    'is_default' => true,
                ]
            );
        }

        return $admin;
    }

    protected function seedCategories()
    {
        Category::query()->where('slug', 'skincare')->update([
            'name' => 'Face Care',
            'slug' => 'face-care',
            'description' => 'Explore our Face Care collection.',
        ]);

        $names = [
            'Face Care', 'Body Care', 'Hair Care', 'Fragrance', 'Makeup',
            'Bath & Ritual', 'Sun Care', 'Men\'s Grooming', 'Gift Sets', 'Wellness Oils',
        ];

        $cats = collect();
        foreach ($names as $i => $name) {
            $slug = Str::slug($name);
            $cats->push(Category::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => "Explore our {$name} collection.",
                    'image_path' => "images/categories/{$slug}.svg",
                    'sort_order' => $i + 1,
                    'is_active' => true,
                    'is_featured' => $i < 4,
                ]
            ));
        }

        return $cats;
    }

    protected function seedBrands()
    {
        $names = [
            'Nivea',
            'Veet',
            'Johnson',
            'Adidas',
            'Vaseline',
            'Speed Stick',
            'Loreal',
            'Venus',
            'Gillette',
        ];

        $brands = collect();
        $slugs = [];
        foreach ($names as $i => $name) {
            $slug = Str::slug($name);
            $slugs[] = $slug;
            $brands->push(Brand::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => "{$name} — curated for Aura & Oath.",
                    'logo_path' => 'images/placeholders/brand.svg',
                    'is_active' => true,
                    'is_featured' => $i < 5,
                    'sort_order' => $i + 1,
                ]
            ));
        }

        Brand::query()->whereNotIn('slug', $slugs)->delete();

        return $brands;
    }

    protected function seedAttributes(): array
    {
        $shade = Attribute::query()->updateOrCreate(
            ['slug' => 'shade'],
            ['name' => 'Shade', 'type' => 'color', 'is_variant' => true, 'is_filterable' => true]
        );
        $size = Attribute::query()->updateOrCreate(
            ['slug' => 'size'],
            ['name' => 'Size', 'type' => 'select', 'is_variant' => true, 'is_filterable' => true]
        );
        $scent = Attribute::query()->updateOrCreate(
            ['slug' => 'scent'],
            ['name' => 'Scent', 'type' => 'select', 'is_variant' => true, 'is_filterable' => true]
        );
        $unit = Attribute::query()->updateOrCreate(
            ['slug' => 'unit'],
            ['name' => 'Unit', 'type' => 'unit', 'is_variant' => false, 'is_filterable' => false, 'sort_order' => 10]
        );

        $shadeValues = [];
        foreach ([['Ivory', '#F7F3EE'], ['Blush', '#D4A5A5'], ['Sand', '#C4A574'], ['Rose', '#C98B8B']] as $i => [$v, $hex]) {
            $shadeValues[] = AttributeValue::query()->updateOrCreate(
                ['attribute_id' => $shade->id, 'slug' => Str::slug($v)],
                ['value' => $v, 'color_hex' => $hex, 'sort_order' => $i]
            );
        }

        $sizeValues = [];
        foreach (['30ml', '50ml', '100ml'] as $i => $v) {
            $sizeValues[] = AttributeValue::query()->updateOrCreate(
                ['attribute_id' => $size->id, 'slug' => Str::slug($v)],
                ['value' => $v, 'sort_order' => $i]
            );
        }

        $scentValues = [];
        foreach (['Amber', 'Fig', 'Jasmine', 'Cedar'] as $i => $v) {
            $scentValues[] = AttributeValue::query()->updateOrCreate(
                ['attribute_id' => $scent->id, 'slug' => Str::slug($v)],
                ['value' => $v, 'sort_order' => $i]
            );
        }

        $unitValues = [];
        foreach (['ml', 'g'] as $i => $v) {
            $unitValues[] = AttributeValue::query()->updateOrCreate(
                ['attribute_id' => $unit->id, 'slug' => Str::slug($v)],
                ['value' => $v, 'sort_order' => $i]
            );
        }

        return compact('shade', 'size', 'scent', 'unit', 'shadeValues', 'sizeValues', 'scentValues', 'unitValues');
    }

    protected function seedProducts($categories, $brands, array $attributes)
    {
        $catalog = [
            ['Silk Serum Glow', 'Hydrating face serum with quiet radiance.', 48.00, true, true],
            ['Velvet Night Cream', 'Restore overnight with botanical lipids.', 54.00, true, false],
            ['Ivory Cleansing Balm', 'Melt away the day without stripping.', 36.00, false, true],
            ['Soft Blush Mist', 'A refreshing facial mist for midday calm.', 28.00, true, true],
            ['Oath Body Oil', 'Nourish skin with warm amber notes.', 42.00, false, false],
            ['Quiet Hair Mask', 'Deep repair for soft, luminous hair.', 34.00, true, false],
            ['Cedar Rose Eau', 'A soft floral fragrance for dusk.', 89.00, true, true],
        ];

        $products = collect();
        $barcodeSeq = 6221000000000;
        $keptSkus = [];

        foreach ($catalog as $i => [$name, $desc, $price, $featured, $bestseller]) {
            $sku = 'AO-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
            $keptSkus[] = $sku;
            $barcode = (string) ($barcodeSeq + $i);
            $stock = 10 + (($i * 7) % 61); // deterministic 10–70

            $product = Product::query()->updateOrCreate(
                ['sku' => $sku],
                [
                    'category_id' => $categories[$i % $categories->count()]->id,
                    'brand_id' => $brands[$i % $brands->count()]->id,
                    'name' => $name,
                    'slug' => Str::slug($name).'-'.($i + 1),
                    'barcode' => $barcode,
                    'short_description' => $desc,
                    'description' => $desc.' Crafted for the Aura & Oath ritual — warm, refined, and quietly luxurious.',
                    'ingredients' => 'Aqua, Glycerin, Botanical Extracts, Tocopherol.',
                    'how_to_use' => 'Apply morning and evening to clean skin. Pat gently until absorbed.',
                    'price' => $price,
                    'compare_at_price' => $featured ? round($price * 1.18, 2) : null,
                    'cost_price' => round($price * 0.45, 2),
                    'stock_quantity' => $stock,
                    'reserved_quantity' => 0,
                    'low_stock_threshold' => 5,
                    'track_inventory' => true,
                    'has_variants' => false,
                    'status' => ProductStatus::Active,
                    'visibility' => ProductVisibility::Public,
                    'gender' => match ($i % 3) {
                        0 => ProductGender::Women,
                        1 => ProductGender::Men,
                        default => ProductGender::Unisex,
                    },
                    'stock_status' => $stock <= 5 ? StockStatus::LowStock : StockStatus::InStock,
                    'is_featured' => $featured,
                    'is_bestseller' => $bestseller,
                    'is_new' => $i >= 5,
                    'size' => [30, 50, 100][$i % 3],
                    'unit' => $i % 5 === 0 ? 'g' : 'ml',
                    'published_at' => now()->subDays(rand(1, 60)),
                ]
            );

            $product->categories()->sync([$categories[$i % $categories->count()]->id]);

            ProductImage::query()->updateOrCreate(
                ['product_id' => $product->id, 'is_primary' => true],
                [
                    'path' => 'images/placeholders/product.svg',
                    'alt' => $product->name,
                    'sort_order' => 0,
                ]
            );

            // Variants for a few products (size)
            if (in_array($i, [0, 6], true)) {
                $product->update(['has_variants' => true, 'stock_quantity' => 0]);
                $valueSet = $attributes['sizeValues'];
                $attr = $attributes['size'];
                foreach ($valueSet as $vi => $attrVal) {
                    $vSku = $sku.'-V'.($vi + 1);
                    $vBarcode = (string) ($barcodeSeq + 1000 + ($i * 10) + $vi);
                    $variant = ProductVariant::query()->updateOrCreate(
                        ['sku' => $vSku],
                        [
                            'product_id' => $product->id,
                            'name' => $attrVal->value,
                            'barcode' => $vBarcode,
                            'price' => round($price + ($vi * 5), 2),
                            'cost_price' => round(($price + ($vi * 5)) * 0.45, 2),
                            'stock_quantity' => 8 + (($i + $vi) * 3) % 35,
                            'reserved_quantity' => 0,
                            'low_stock_threshold' => 5,
                            'is_active' => true,
                        ]
                    );
                    $variant->attributeValues()->sync([
                        $attrVal->id => ['attribute_id' => $attr->id],
                    ]);
                }
                $product->refreshStockStatus();
            }

            $products->push($product->fresh('variants'));
        }

        Product::query()->whereNotIn('sku', $keptSkus)->delete();

        return $products;
    }

    protected function seedDeliveryRegions(): void
    {
        $regions = [
            [
                'name' => 'Beirut Central',
                'code' => 'BRT',
                'fee' => 3.00,
                'min' => 1,
                'max' => 2,
                'description' => 'Hamra, Achrafieh, Verdun, Mar Mikhael, Mazraa, Rawche, Jnah, Downtown, Ras Beirut, Gemmayze, Badaro, Dahye.',
            ],
            [
                'name' => 'Metn & Coastal Suburbs',
                'code' => 'MET',
                'fee' => 5.00,
                'min' => 1,
                'max' => 3,
                'description' => 'Zalka, Jal el Dib, Antelias, Kaslik, Tabarja, Baabda, Khalde, Bchamoun, Aramoun, Dbayeh, Naccache, Fanar.',
            ],
            [
                'name' => 'Mountain & Southern Coastal',
                'code' => 'MSC',
                'fee' => 6.00,
                'min' => 2,
                'max' => 4,
                'description' => 'Aley, Saida (Sidon), Ghazieh, Zahle, Jbeil (Byblos), Kfardebian, Mtein, Bickfaya, Dhour El Choueir.',
            ],
            [
                'name' => 'Major Cities North & South',
                'code' => 'MNS',
                'fee' => 7.00,
                'min' => 2,
                'max' => 5,
                'description' => 'Tripoli (Trablos), Koura, El Mina, Chtaura, Sour (Tyre), Hasbaya, Marjeyoun, Jezzine, Batroun.',
            ],
            [
                'name' => 'Remote & Eastern Districts',
                'code' => 'REM',
                'fee' => 8.00,
                'min' => 3,
                'max' => 6,
                'description' => 'Baalbek, Bint Jbeil, Nabatieh, Hermel, Rashaya, Qaa, Deir El Ahmar, Aarsal.',
            ],
        ];

        foreach ($regions as $i => $region) {
            DeliveryRegion::query()->updateOrCreate(
                ['code' => $region['code']],
                [
                    'name' => $region['name'],
                    'fee' => $region['fee'],
                    'description' => $region['description'],
                    'estimated_days_min' => $region['min'],
                    'estimated_days_max' => $region['max'],
                    'is_active' => true,
                    'sort_order' => $i + 1,
                ]
            );
        }

        DeliveryRegion::query()
            ->whereNotIn('code', collect($regions)->pluck('code')->all())
            ->update(['is_active' => false]);
    }

    protected function seedCoupons(): void
    {
        Coupon::query()->where('code', 'OATH50')->delete();

        Coupon::query()->updateOrCreate(
            ['code' => 'AURA10'],
            [
                'name' => '10% Welcome',
                'discount_type' => DiscountType::Percentage,
                'discount_value' => 10,
                'min_order_amount' => 35,
                'max_discount_amount' => 25,
                'usage_limit' => 500,
                'usage_limit_per_user' => 2,
                'is_active' => true,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(6),
            ]
        );

        Coupon::query()->updateOrCreate(
            ['code' => 'OATH10'],
            [
                'name' => '$10 Off',
                'discount_type' => DiscountType::Fixed,
                'discount_value' => 10,
                'min_order_amount' => 50,
                'is_active' => true,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(3),
            ]
        );
    }

    protected function seedBanners(): void
    {
        Banner::query()->updateOrCreate(
            ['title' => 'Quiet Luxury for Skin & Ritual'],
            [
                'subtitle' => 'Discover Aura & Oath — premium beauty, softly composed.',
                'image_path' => 'images/home-hero.png',
                'link_url' => '/shop',
                'button_text' => 'Shop the collection',
                'placement' => 'home_hero',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        Banner::query()->updateOrCreate(
            ['title' => 'New Arrivals'],
            [
                'subtitle' => 'Fresh textures, muted tones, lasting care.',
                'image_path' => 'images/home-hero.png',
                'link_url' => '/shop?sort=newest',
                'button_text' => 'Explore new',
                'placement' => 'home_secondary',
                'sort_order' => 2,
                'is_active' => true,
            ]
        );
    }

    protected function seedPages(): void
    {
        foreach ([
            ['Privacy Policy', 'privacy-policy', 'We respect your privacy. This policy explains how Aura & Oath collects and uses information.'],
            ['Terms of Service', 'terms-of-service', 'By shopping with Aura & Oath you agree to these terms.'],
            ['Shipping Policy', 'shipping-policy', 'We deliver across Lebanon. Fees depend on your area: Beirut Central ($3), Metn & Coastal Suburbs ($5), Mountain & Southern Coastal ($6), Major Cities North & South ($7), and Remote & Eastern Districts ($8). Delivery takes 1–3 business days depending on distance.'],
            ['Returns Policy', 'returns-policy', 'Returns are accepted for eligible items within 24 hours, subject to inspection.'],
        ] as [$title, $slug, $content]) {
            Page::query()->updateOrCreate(
                ['slug' => $slug],
                ['title' => $title, 'content' => $content, 'is_published' => true]
            );
        }
    }

    protected function seedOrders(User $admin, $products): void
    {
        $customers = User::query()->where('role', UserRole::Customer)->get();
        $region = DeliveryRegion::query()->first();
        $statuses = [
            OrderStatus::PendingApproval,
            OrderStatus::Approved,
            OrderStatus::Preparing,
            OrderStatus::OnTheWay,
            OrderStatus::Delivered,
            OrderStatus::Cancelled,
            OrderStatus::Returned,
            OrderStatus::Refunded,
        ];

        foreach ($statuses as $i => $status) {
            $customer = $customers[$i % $customers->count()];
            $product = $products->first(fn ($p) => ! $p->has_variants) ?? $products[$i % $products->count()];
            $qty = 1 + ($i % 2);
            $unit = (float) $product->price;
            $subtotal = $unit * $qty;
            $delivery = (float) ($region->fee ?? 5.99);
            $total = $subtotal + $delivery;

            $order = Order::query()->updateOrCreate(
                ['order_number' => 'AO-SEED-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'idempotency_token' => (string) Str::uuid(),
                    'user_id' => $customer->id,
                    'status' => $status,
                    'payment_method' => PaymentMethod::CashOnDelivery,
                    'payment_status' => PaymentStatus::Pending,
                    'currency' => 'USD',
                    'subtotal' => $subtotal,
                    'discount_amount' => 0,
                    'delivery_fee' => $delivery,
                    'tax_amount' => 0,
                    'total' => $total,
                    'delivery_region_id' => $region?->id,
                    'customer_name' => $customer->name,
                    'customer_email' => $customer->email,
                    'customer_phone' => $customer->phone,
                    'approved_at' => in_array($status, [OrderStatus::PendingApproval, OrderStatus::Cancelled], true) ? null : now()->subDays(2),
                    'approved_by' => in_array($status, [OrderStatus::PendingApproval, OrderStatus::Cancelled], true) ? null : $admin->id,
                    'cancelled_at' => $status === OrderStatus::Cancelled ? now()->subDay() : null,
                ]
            );

            if ($order->items()->count() === 0) {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'line_total' => $subtotal,
                    'unit_cost' => $product->cost_price,
                ]);

                OrderAddress::query()->create([
                    'order_id' => $order->id,
                    'type' => 'shipping',
                    'full_name' => $customer->name,
                    'phone' => $customer->phone,
                    'line1' => '12 Hamra Street',
                    'city' => 'Beirut',
                    'governorate' => 'Beirut',
                    'country' => 'LB',
                ]);

                OrderStatusHistory::query()->create([
                    'order_id' => $order->id,
                    'from_status' => null,
                    'to_status' => $status,
                    'changed_by' => $admin->id,
                    'note' => 'Seeded order',
                    'is_customer_visible' => true,
                ]);
            }

            // Pending seed orders should hold reserved stock like live checkout.
            if ($status === OrderStatus::PendingApproval && $product->track_inventory && ! $product->has_variants) {
                $fresh = $product->fresh();
                if ((int) $fresh->reserved_quantity < $qty) {
                    $fresh->increment('reserved_quantity', $qty - (int) $fresh->reserved_quantity);
                    $fresh->refreshStockStatus();
                }
            }
        }
    }
}
