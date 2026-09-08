<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function createProduct(array $overrides = []): Product
    {
        return Product::query()->create(array_merge([
            'name' => 'SEO Test Serum',
            'slug' => 'seo-test-serum',
            'sku' => 'SKU-SEO-001',
            'barcode' => 'BC-SEO-001',
            'price' => 42.50,
            'cost_price' => 20,
            'stock_quantity' => 10,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 2,
            'track_inventory' => true,
            'has_variants' => false,
            'status' => ProductStatus::Active,
            'visibility' => ProductVisibility::Public,
            'stock_status' => 'in_stock',
            'short_description' => 'A quietly confident serum for daily glow.',
            'published_at' => now(),
        ], $overrides));
    }

    public function test_homepage_has_canonical_title_and_organization_json_ld(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<link rel="canonical"', false);
        $response->assertSee('Aura &amp; Oath', false);
        $response->assertSee('"@type":"Organization"', false);
        $response->assertSee('"@type":"WebSite"', false);
        $response->assertSee('rel="alternate" hreflang="en"', false);
        $response->assertSee('rel="alternate" hreflang="ar"', false);
    }

    public function test_sitemap_is_available_and_lists_products(): void
    {
        $this->createProduct(['slug' => 'sitemap-serum']);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false);
        $response->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"', false);
        $response->assertSee('<loc>'.route('products.show', 'sitemap-serum').'</loc>', false);
        $response->assertSee('hreflang="en"', false);
        $response->assertSee('hreflang="ar"', false);
        $response->assertHeaderMissing('Set-Cookie');
    }

    public function test_robots_txt_points_to_sitemap_and_blocks_admin(): void
    {
        $contents = file_get_contents(public_path('robots.txt'));

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('Sitemap:', $contents);
        $this->assertStringContainsString('https://auraandoath.com/sitemap.xml', $contents);
        $this->assertStringContainsString('Disallow: /admin', $contents);
    }

    public function test_product_page_has_product_json_ld_and_unique_title(): void
    {
        $product = $this->createProduct([
            'name' => 'Radiance Night Serum',
            'slug' => 'radiance-night-serum',
            'meta_title' => 'Radiance Night Serum | Aura & Oath',
        ]);

        $response = $this->get(route('products.show', $product->slug));

        $response->assertOk();
        $response->assertSee('Radiance Night Serum | Aura &amp; Oath', false);
        $response->assertSee('"@type":"Product"', false);
        $response->assertSee('"sku":"SKU-SEO-001"', false);
        $response->assertSee('"priceCurrency":"'.config('aura.currency', 'USD').'"', false);
        $response->assertSee('<link rel="canonical"', false);
        $response->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_cart_page_is_noindex(): void
    {
        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }
}
