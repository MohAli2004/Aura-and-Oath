<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function seedLegalPages(): void
    {
        foreach (['privacy-policy', 'terms-of-service'] as $slug) {
            Page::query()->create([
                'title' => str($slug)->headline()->toString(),
                'slug' => $slug,
                'content' => 'Sample legal copy for '.$slug.'.',
                'is_published' => true,
            ]);
        }
    }

    protected function createProduct(): Product
    {
        return Product::query()->create([
            'name' => 'Trust Test Serum',
            'slug' => 'trust-test-serum',
            'sku' => 'SKU-TRUST',
            'barcode' => 'BC-TRUST',
            'price' => 50,
            'cost_price' => 20,
            'stock_quantity' => 10,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 2,
            'track_inventory' => true,
            'has_variants' => false,
            'status' => 'active',
            'visibility' => 'public',
            'stock_status' => 'in_stock',
            'published_at' => now(),
        ]);
    }

    protected function setPaymentFlags(bool $cod, bool $wish): void
    {
        $settings = app(SettingsService::class);
        $settings->set('payment_cod_enabled', $cod, 'boolean', 'payments', true);
        $settings->set('payment_wish_enabled', $wish, 'boolean', 'payments', true);
    }

    public function test_privacy_and_terms_pages_return_ok(): void
    {
        $this->seedLegalPages();

        $this->get('/pages/privacy-policy')->assertOk()->assertSee('Sample legal copy for privacy-policy');
        $this->get('/pages/terms-of-service')->assertOk()->assertSee('Sample legal copy for terms-of-service');
    }

    public function test_home_shows_trust_facts_when_payment_flags_on(): void
    {
        $this->setPaymentFlags(true, true);

        $this->get('/')
            ->assertOk()
            ->assertSee('Delivery across Lebanon')
            ->assertSee('Cash on Delivery')
            ->assertSee('Wish Account')
            ->assertSee('Clear returns');
    }

    public function test_home_hides_disabled_payment_methods_from_trust_facts(): void
    {
        $this->setPaymentFlags(false, true);

        $response = $this->get('/')->assertOk();

        $response->assertSee('Wish Account')
            ->assertDontSee('Cash on Delivery');
    }

    public function test_product_page_shows_trust_copy_respecting_flags(): void
    {
        $this->setPaymentFlags(true, false);
        $product = $this->createProduct();

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee('Delivery across Lebanon')
            ->assertSee('Cash on Delivery')
            ->assertSee('Returns within 24 hours')
            ->assertDontSee('Wish Account');
    }

    public function test_footer_links_to_privacy_and_terms(): void
    {
        $this->seedLegalPages();

        $this->get('/')
            ->assertOk()
            ->assertSee(route('pages.show', 'privacy-policy'), false)
            ->assertSee(route('pages.show', 'terms-of-service'), false);
    }

    public function test_cart_shows_checkout_guidance_for_guests(): void
    {
        $product = $this->createProduct();

        $this->postJson(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Sign in to checkout so we can save your order')
            ->assertSee('Secure checkout on this site');
    }

    public function test_checkout_shows_trust_copy_for_signed_in_customer(): void
    {
        $this->seedLegalPages();
        $this->setPaymentFlags(true, true);

        $customer = User::factory()->customer()->create();
        $product = $this->createProduct();

        $this->actingAs($customer);
        $this->postJson(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $this->get(route('checkout.create'))
            ->assertOk()
            ->assertSee('Privacy policy')
            ->assertSee('Prices shown in your summary include the delivery fee')
            ->assertSee('Cash on delivery available');
    }
}
