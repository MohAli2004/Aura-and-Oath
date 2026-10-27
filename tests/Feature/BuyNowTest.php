<?php

namespace Tests\Feature;

use App\Enums\ContentSource;
use App\Enums\PaymentMethod;
use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Models\Cart;
use App\Models\DeliveryRegion;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyNowTest extends TestCase
{
    use RefreshDatabase;

    public function test_buy_now_buttons_are_on_product_cards_and_product_page(): void
    {
        $product = $this->product(['is_featured' => true, 'is_new' => true]);

        $this->get('/')->assertOk()->assertSee(__('storefront.buy_now'), false);
        $this->get(route('products.show', $product->slug))->assertOk()->assertSee(__('storefront.buy_now'), false);
    }

    public function test_buy_now_does_not_touch_the_bag_and_checks_out_only_that_item(): void
    {
        $user = User::factory()->customer()->create();
        $inBag = $this->product(['name' => 'Already In Bag', 'sku' => 'SKU-BAG', 'barcode' => 'BC-BAG']);
        $direct = $this->product(['name' => 'Direct Buy', 'sku' => 'SKU-DIRECT', 'barcode' => 'BC-DIRECT', 'stock_quantity' => 5]);
        $region = DeliveryRegion::query()->create(['name' => 'Beirut', 'code' => 'BEI', 'fee' => 3, 'is_active' => true]);

        $this->actingAs($user);
        app(CartService::class)->add($inBag, 1);

        $this->postJson(route('buy-now.store'), ['product_id' => $direct->id, 'quantity' => 2])
            ->assertOk()
            ->assertJsonPath('redirect', route('checkout.create'));

        $bag = Cart::query()->where('user_id', $user->id)->where('is_buy_now', false)->firstOrFail();
        $this->assertSame(1, $bag->items()->count());
        $this->assertSame($inBag->id, $bag->items()->first()->product_id);

        $this->get(route('checkout.create'))
            ->assertOk()
            ->assertSee('Direct Buy')
            ->assertDontSee('Already In Bag')
            ->assertSee(__('storefront.buy_now_notice'));

        $this->post(route('checkout.store'), [
            'payment_method' => PaymentMethod::CashOnDelivery->value,
            'delivery_region_id' => $region->id,
            'idempotency_token' => 'buy-now-1',
            'terms_agreed' => '1',
            'shipping' => ['full_name' => $user->name, 'phone' => '010', 'line1' => 'A', 'city' => 'Beirut'],
        ])->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame(1, $order->items()->count());
        $this->assertSame($direct->id, $order->items()->first()->product_id);
        $this->assertSame(2, (int) $order->items()->first()->quantity);

        $this->assertSame(1, $bag->fresh()->items()->count());
        $this->assertSame(0, Cart::query()->where('is_buy_now', true)->count());
    }

    public function test_viewing_the_bag_ends_buy_now_and_checkout_uses_the_bag_again(): void
    {
        $user = User::factory()->customer()->create();
        $inBag = $this->product(['name' => 'Bag Item', 'sku' => 'SKU-B2', 'barcode' => 'BC-B2']);
        $direct = $this->product(['name' => 'Direct Item', 'sku' => 'SKU-D2', 'barcode' => 'BC-D2']);

        $this->actingAs($user);
        app(CartService::class)->add($inBag, 1);
        $this->postJson(route('buy-now.store'), ['product_id' => $direct->id])->assertOk();

        $this->get(route('cart.index'))->assertOk();
        $this->get(route('checkout.create'))->assertOk()->assertSee('Bag Item')->assertDontSee('Direct Item');
    }

    public function test_buy_now_rejects_unavailable_quantity(): void
    {
        $product = $this->product(['stock_quantity' => 1]);

        $this->postJson(route('buy-now.store'), ['product_id' => $product->id, 'quantity' => 5])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function product(array $overrides = []): Product
    {
        return Product::query()->create(array_merge([
            'name' => 'Buy Serum',
            'slug' => 'buy-serum-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'barcode' => 'BC'.uniqid(),
            'price' => 20,
            'cost_price' => 8,
            'stock_quantity' => 10,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 2,
            'track_inventory' => true,
            'has_variants' => false,
            'status' => ProductStatus::Active,
            'visibility' => ProductVisibility::Public,
            'stock_status' => 'in_stock',
            'content_source' => ContentSource::Verified,
            'content_approved_at' => now(),
            'published_at' => now(),
        ], $overrides));
    }
}
