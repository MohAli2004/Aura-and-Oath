<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Models\DeliveryRegion;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\BarcodeService;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuraCommerceTest extends TestCase
{
    use RefreshDatabase;

    protected function createProduct(array $overrides = []): Product
    {
        return Product::query()->create(array_merge([
            'name' => 'Test Serum',
            'slug' => 'test-serum-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'barcode' => 'BC'.uniqid(),
            'price' => 100,
            'cost_price' => 40,
            'stock_quantity' => 10,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 2,
            'track_inventory' => true,
            'has_variants' => false,
            'status' => ProductStatus::Active,
            'visibility' => ProductVisibility::Public,
            'stock_status' => 'in_stock',
            'published_at' => now(),
        ], $overrides));
    }

    protected function createRegion(string $code = 'BEI'): DeliveryRegion
    {
        return DeliveryRegion::query()->create([
            'name' => 'Beirut Central',
            'code' => $code,
            'fee' => 3,
            'description' => 'Hamra, Achrafieh, Verdun, Mar Mikhael, Mazraa, Rawche, Jnah, Downtown, Ras Beirut, Gemmayze, Badaro, Dahye.',
            'is_active' => true,
        ]);
    }

    public function test_guest_can_view_home_and_shop(): void
    {
        $this->createProduct(['name' => 'Shop Serum', 'slug' => 'shop-serum', 'sku' => 'SKU-SHOP', 'barcode' => 'BC-SHOP']);

        $this->get('/')->assertOk();
        $this->get('/shop')->assertOk()->assertSee('Shop Serum');
    }

    public function test_customer_can_register_and_login(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        $this->post('/login', [
            'email' => 'testuser@example.com',
            'password' => 'password',
        ])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_admin_routes_are_protected(): void
    {
        $this->get('/admin')->assertRedirect('/login');

        $customer = User::factory()->customer()->create();
        $this->actingAs($customer)->get('/admin')->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_product_crud_admin_and_unique_barcode_and_sku(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->post('/admin/products', [
            'name' => 'Cream',
            'sku' => 'AO-TEST-1',
            'barcode' => '6229990000001',
            'price' => 200,
            'stock_quantity' => 5,
            'status' => 'active',
            'visibility' => 'public',
            'track_inventory' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('products', ['sku' => 'AO-TEST-1', 'barcode' => '6229990000001']);

        $this->post('/admin/products', [
            'name' => 'Cream 2',
            'sku' => 'AO-TEST-2',
            'barcode' => '6229990000001',
            'price' => 200,
            'stock_quantity' => 5,
            'status' => 'active',
            'visibility' => 'public',
        ])->assertSessionHasErrors('barcode');

        $this->post('/admin/products', [
            'name' => 'Cream 3',
            'sku' => 'AO-TEST-1',
            'barcode' => '6229990000009',
            'price' => 200,
            'stock_quantity' => 5,
            'status' => 'active',
            'visibility' => 'public',
        ])->assertSessionHasErrors('sku');
    }

    public function test_barcode_service_lookup_and_format(): void
    {
        $product = $this->createProduct(['barcode' => '6221000000123', 'sku' => 'AO-LOOK']);
        $service = app(BarcodeService::class);

        $result = $service->lookup('6221000000123');
        $this->assertNotNull($result);
        $this->assertEquals($product->id, $result['product']->id);
        $this->assertEquals('EAN-13', $service->detectFormat('6221000000123'));

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get('/admin/barcodes?barcode=6221000000123')
            ->assertOk()
            ->assertSee('AO-LOOK');
    }

    public function test_cart_add_update_remove(): void
    {
        $product = $this->createProduct();
        $user = User::factory()->customer()->create();
        $this->actingAs($user);

        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
        $cart = app(CartService::class)->getOrCreateCart($user);
        $this->assertEquals(2, $cart->fresh()->items()->first()->quantity);

        $item = $cart->items()->first();
        $this->patch('/cart/'.$item->id, ['quantity' => 3])->assertRedirect();
        $this->assertEquals(3, $item->fresh()->quantity);

        $this->delete('/cart/'.$item->id)->assertRedirect();
        $this->assertEquals(0, $cart->fresh()->items()->count());
    }

    public function test_guest_cart_merges_on_login(): void
    {
        $product = $this->createProduct(['sku' => 'SKU-MERGE', 'barcode' => 'BC-MERGE']);

        $this->post('/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertRedirect();

        $this->assertEquals(2, app(CartService::class)->count());

        $user = User::factory()->customer()->create([
            'email' => 'merge@example.com',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'merge@example.com',
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $userCart = app(CartService::class)->getOrCreateCart($user->fresh());
        $this->assertGreaterThanOrEqual(2, (int) $userCart->items()->sum('quantity'));
    }

    public function test_checkout_reserves_stock_and_is_idempotent(): void
    {
        $product = $this->createProduct(['stock_quantity' => 10]);
        $user = User::factory()->customer()->create();
        $region = $this->createRegion('BEI');

        $this->actingAs($user);
        app(CartService::class)->add($product, 2);

        $payload = [
            'customer_phone' => '01000000000',
            'payment_method' => PaymentMethod::CashOnDelivery->value,
            'delivery_region_id' => $region->id,
            'idempotency_token' => 'idem-token-123',
            'shipping' => [
                'full_name' => $user->name,
                'phone' => '01000000000',
                'line1' => 'Street 1',
                'city' => 'Beirut',
            ],
        ];

        $checkout = app(CheckoutService::class);
        $order1 = $checkout->placeOrder($user, $payload);
        $order2 = $checkout->placeOrder($user, $payload);

        $this->assertEquals($order1->id, $order2->id);
        $this->assertEquals(OrderStatus::PendingApproval, $order1->status);
        $this->assertEquals(2, $product->fresh()->reserved_quantity);
        $this->assertEquals(8, $product->fresh()->availableStock());
        $this->assertEquals(100.0, (float) $order1->items()->first()->unit_price);
    }

    public function test_checkout_never_trusts_client_prices(): void
    {
        $product = $this->createProduct(['price' => 150, 'stock_quantity' => 5]);
        $user = User::factory()->customer()->create();
        $region = $this->createRegion('BEI-P');

        $this->actingAs($user);
        app(CartService::class)->add($product, 1);

        $order = app(CheckoutService::class)->placeOrder($user, [
            'customer_phone' => '01000000000',
            'payment_method' => PaymentMethod::CashOnDelivery->value,
            'delivery_region_id' => $region->id,
            'idempotency_token' => 'price-token',
            'shipping' => ['full_name' => $user->name, 'phone' => '010', 'line1' => 'A', 'city' => 'Beirut'],
            // Ignored if somehow present:
            'unit_price' => 1,
            'total' => 1,
        ]);

        $this->assertEquals(150.0, (float) $order->items()->first()->unit_price);
        $this->assertEquals(200.0, (float) $order->total); // 150 + 50 delivery
    }

    public function test_approve_converts_reservation_and_reject_releases(): void
    {
        $product = $this->createProduct(['stock_quantity' => 10]);
        $admin = User::factory()->admin()->create();
        $user = User::factory()->customer()->create();
        $region = $this->createRegion('BEI2');

        $this->actingAs($user);
        app(CartService::class)->add($product, 3);
        $order = app(CheckoutService::class)->placeOrder($user, [
            'customer_phone' => '01000000000',
            'payment_method' => PaymentMethod::CashOnDelivery->value,
            'delivery_region_id' => $region->id,
            'idempotency_token' => 'approve-test-1',
            'shipping' => ['full_name' => $user->name, 'phone' => '010', 'line1' => 'A', 'city' => 'Beirut'],
        ]);

        $this->assertEquals(3, $product->fresh()->reserved_quantity);

        app(OrderService::class)->approve($order, $admin);
        $product->refresh();
        $this->assertEquals(0, $product->reserved_quantity);
        $this->assertEquals(7, $product->stock_quantity);
        $this->assertEquals(OrderStatus::Approved, $order->fresh()->status);

        $product2 = $this->createProduct(['stock_quantity' => 5, 'sku' => 'SKU-R', 'barcode' => 'BC-R', 'slug' => 'reject-p']);
        app(CartService::class)->add($product2, 2);
        $order2 = app(CheckoutService::class)->placeOrder($user, [
            'customer_phone' => '01000000000',
            'payment_method' => PaymentMethod::CashOnDelivery->value,
            'delivery_region_id' => $region->id,
            'idempotency_token' => 'reject-test-1',
            'shipping' => ['full_name' => $user->name, 'phone' => '010', 'line1' => 'A', 'city' => 'Beirut'],
        ]);
        app(OrderService::class)->reject($order2, $admin, 'Out of season');
        $this->assertEquals(0, $product2->fresh()->reserved_quantity);
        $this->assertEquals(5, $product2->fresh()->stock_quantity);
    }

    public function test_customer_cancel_pending_releases_stock(): void
    {
        $product = $this->createProduct(['stock_quantity' => 6]);
        $user = User::factory()->customer()->create();
        $region = $this->createRegion('BEI3');

        $this->actingAs($user);
        app(CartService::class)->add($product, 2);
        $order = app(CheckoutService::class)->placeOrder($user, [
            'customer_phone' => '01000000000',
            'payment_method' => PaymentMethod::CashOnDelivery->value,
            'delivery_region_id' => $region->id,
            'idempotency_token' => 'cancel-test-1',
            'shipping' => ['full_name' => $user->name, 'phone' => '010', 'line1' => 'A', 'city' => 'Beirut'],
        ]);

        $this->post('/account/orders/'.$order->id.'/cancel')->assertRedirect();
        $this->assertEquals(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertEquals(0, $product->fresh()->reserved_quantity);
        $this->assertEquals(6, $product->fresh()->stock_quantity);
    }

    public function test_return_restores_stock_when_resellable(): void
    {
        $product = $this->createProduct(['stock_quantity' => 10]);
        $admin = User::factory()->admin()->create();
        $user = User::factory()->customer()->create();
        $region = $this->createRegion('BEI4');

        $this->actingAs($user);
        app(CartService::class)->add($product, 2);
        $order = app(CheckoutService::class)->placeOrder($user, [
            'customer_phone' => '01000000000',
            'payment_method' => PaymentMethod::CashOnDelivery->value,
            'delivery_region_id' => $region->id,
            'idempotency_token' => 'return-test-1',
            'shipping' => ['full_name' => $user->name, 'phone' => '010', 'line1' => 'A', 'city' => 'Beirut'],
        ]);

        $orders = app(OrderService::class);
        $orders->approve($order, $admin);
        $orders->updateStatus($order->fresh(), OrderStatus::Preparing, $admin);
        $orders->updateStatus($order->fresh(), OrderStatus::ReadyForDispatch, $admin);
        $orders->updateStatus($order->fresh(), OrderStatus::Shipped, $admin);
        $orders->confirmReturnResellable($order->fresh(), $admin, true, 'Resellable');

        $this->assertEquals(10, $product->fresh()->stock_quantity);
        $this->assertEquals(OrderStatus::Returned, $order->fresh()->status);
    }

    public function test_inventory_never_goes_negative(): void
    {
        $product = $this->createProduct(['stock_quantity' => 2]);
        $admin = User::factory()->admin()->create();

        $this->expectException(\RuntimeException::class);
        app(InventoryService::class)->adjust(
            $product,
            -5,
            InventoryMovementType::AdjustmentReduce,
            null,
            $admin
        );
    }

    public function test_insufficient_stock_cannot_be_reserved(): void
    {
        $product = $this->createProduct(['stock_quantity' => 1]);
        $user = User::factory()->customer()->create();

        $this->actingAs($user);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CartService::class)->add($product, 5);
    }

    public function test_customer_cannot_view_other_orders_idor(): void
    {
        $owner = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $order = Order::query()->create([
            'order_number' => 'AO-IDOR-1',
            'user_id' => $owner->id,
            'status' => OrderStatus::PendingApproval,
            'payment_method' => PaymentMethod::CashOnDelivery,
            'payment_status' => PaymentStatus::Pending,
            'subtotal' => 100,
            'total' => 100,
            'customer_phone' => '010',
        ]);

        $this->actingAs($other)->get('/account/orders/'.$order->id)->assertForbidden();
        $this->actingAs($owner)->get('/account/orders/'.$order->id)->assertOk();
    }

    public function test_cost_price_hidden_from_product_array(): void
    {
        $product = $this->createProduct(['cost_price' => 55]);
        $this->assertArrayNotHasKey('cost_price', $product->toArray());
    }

    public function test_admin_can_print_invoice_and_labels(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->customer()->create();
        $product = $this->createProduct(['barcode' => '6221999000111']);

        $order = Order::query()->create([
            'order_number' => 'AO-PRINT-1',
            'user_id' => $owner->id,
            'status' => OrderStatus::Approved,
            'payment_method' => PaymentMethod::CashOnDelivery,
            'payment_status' => PaymentStatus::Pending,
            'subtotal' => 100,
            'total' => 100,
            'customer_phone' => '010',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        $this->actingAs($admin)
            ->get('/admin/orders/'.$order->id.'/invoice')
            ->assertOk()
            ->assertSee('AO-PRINT-1');

        $this->actingAs($admin)
            ->get('/admin/orders/'.$order->id.'/packing-slip')
            ->assertOk()
            ->assertSee('Packing Slip');

        $this->actingAs($admin)
            ->get('/admin/barcodes/labels?codes=6221999000111')
            ->assertOk()
            ->assertSee('6221999000111');
    }
}
