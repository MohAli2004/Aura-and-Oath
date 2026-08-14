<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Enums\ReturnRequestStatus;
use App\Models\DeliveryRegion;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderReturnRequestedNotification;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReturnRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function createProduct(array $overrides = []): Product
    {
        return Product::query()->create(array_merge([
            'name' => 'Return Serum',
            'slug' => 'return-serum-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'barcode' => 'BC'.uniqid(),
            'price' => 80,
            'cost_price' => 30,
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

    protected function createRegion(string $code = 'RET'): DeliveryRegion
    {
        return DeliveryRegion::query()->create([
            'name' => 'Beirut Central',
            'code' => $code,
            'fee' => 3,
            'description' => 'Hamra',
            'is_active' => true,
        ]);
    }

    protected function placeDeliveredOrder(User $user, Product $product, User $admin, int $qty = 1): \App\Models\Order
    {
        $region = $this->createRegion('R'.substr(uniqid(), -4));
        $this->actingAs($user);
        app(CartService::class)->add($product, $qty);

        $order = app(CheckoutService::class)->placeOrder($user, [
            'customer_phone' => '01000000000',
            'payment_method' => PaymentMethod::CashOnDelivery->value,
            'delivery_region_id' => $region->id,
            'idempotency_token' => 'return-'.uniqid(),
            'shipping' => ['full_name' => $user->name, 'phone' => '010', 'line1' => 'A', 'city' => 'Beirut'],
        ]);

        $orders = app(OrderService::class);
        $orders->approve($order, $admin);
        $orders->updateStatus($order->fresh(), OrderStatus::OnTheWay, $admin);
        $orders->updateStatus($order->fresh(), OrderStatus::Delivered, $admin);

        return $order->fresh(['items']);
    }

    /** @return array<string, mixed> */
    protected function returnPayload(\App\Models\Order $order, array $overrides = []): array
    {
        $item = $order->items->first();

        return array_merge([
            'order_id' => $order->id,
            'items' => [
                $item->id => [
                    'id' => $item->id,
                    'quantity' => $item->quantity,
                ],
            ],
            'reason' => 'The item arrived damaged in the box.',
            'photo' => $this->fakePhoto(),
            'policy_accepted' => '1',
        ], $overrides);
    }

    protected function fakePhoto(): UploadedFile
    {
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==');

        return UploadedFile::fake()->createWithContent('item.jpg', $jpeg);
    }

    public function test_shipping_is_removed_from_help_and_returns_opens_request_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('shipping-policy', false)
            ->assertSee(route('returns.index'), false);
    }

    public function test_customer_can_request_return_for_delivered_order(): void
    {
        Storage::fake('public');
        Notification::fake();

        $admin = User::factory()->admin()->create(['is_active' => true]);
        $user = User::factory()->customer()->create();
        $product = $this->createProduct();
        $order = $this->placeDeliveredOrder($user, $product, $admin);

        $this->actingAs($user)
            ->from(route('account.orders.show', $order))
            ->post(route('returns.store'), $this->returnPayload($order, [
                'reason' => 'The colour does not match what I expected.',
            ]))
            ->assertRedirect(route('account.orders.show', $order));

        $order->refresh();
        $this->assertEquals(OrderStatus::ReturnRequested, $order->status);
        $this->assertTrue($order->pendingReturnRequest()->exists());
        $this->assertEquals(ReturnRequestStatus::Pending, $order->pendingReturnRequest->status);
        $this->assertNotEmpty($order->pendingReturnRequest->photo_path);

        Notification::assertSentTo($admin, OrderReturnRequestedNotification::class);
    }

    public function test_guest_can_request_return_with_order_number_and_email(): void
    {
        Storage::fake('public');
        Notification::fake();

        $admin = User::factory()->admin()->create(['is_active' => true]);
        $user = User::factory()->customer()->create();
        $product = $this->createProduct();
        $order = $this->placeDeliveredOrder($user, $product, $admin);

        $this->post('/logout');

        $this->from(route('returns.index'))
            ->post(route('returns.store'), $this->returnPayload($order, [
                'order_id' => null,
                'order_number' => $order->order_number,
                'email' => $order->customer_email,
                'reason' => 'Opened but unused — requesting a return.',
            ]))
            ->assertRedirect(route('returns.index', [
                'order_number' => $order->order_number,
                'email' => $order->customer_email,
            ]));

        $this->assertEquals(OrderStatus::ReturnRequested, $order->fresh()->status);
        Notification::assertSentTo($admin, OrderReturnRequestedNotification::class);
    }

    public function test_return_is_rejected_before_delivery_and_after_window(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $user = User::factory()->customer()->create();
        $product = $this->createProduct();
        $region = $this->createRegion('PRE');

        $this->actingAs($user);
        app(CartService::class)->add($product, 1);
        $pending = app(CheckoutService::class)->placeOrder($user, [
            'customer_phone' => '01000000000',
            'payment_method' => PaymentMethod::CashOnDelivery->value,
            'delivery_region_id' => $region->id,
            'idempotency_token' => 'pending-return-1',
            'shipping' => ['full_name' => $user->name, 'phone' => '010', 'line1' => 'A', 'city' => 'Beirut'],
        ]);

        $this->from(route('returns.index'))
            ->post(route('returns.store'), $this->returnPayload($pending, [
                'reason' => 'Changed my mind before it arrived.',
            ]))
            ->assertRedirect(route('returns.index'))
            ->assertSessionHas('error');

        $delivered = $this->placeDeliveredOrder($user, $this->createProduct(['stock_quantity' => 8]), $admin);
        $delivered->forceFill(['delivered_at' => now()->subHours(25)])->save();

        $this->from(route('account.orders.show', $delivered))
            ->post(route('returns.store'), $this->returnPayload($delivered, [
                'reason' => 'Too late, but trying anyway now.',
            ]))
            ->assertRedirect(route('account.orders.show', $delivered))
            ->assertSessionHas('error');
    }

    public function test_admin_can_approve_return_and_restock(): void
    {
        Storage::fake('public');
        Notification::fake();

        $admin = User::factory()->admin()->create(['is_active' => true]);
        $user = User::factory()->customer()->create();
        $product = $this->createProduct(['stock_quantity' => 10]);
        $order = $this->placeDeliveredOrder($user, $product, $admin, 2);

        $this->assertEquals(8, $product->fresh()->stock_quantity);

        $this->actingAs($user)->post(route('returns.store'), $this->returnPayload($order, [
            'reason' => 'Packaging arrived damaged in transit.',
        ]));

        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order))
            ->post(route('admin.orders.return', $order), [
                'resellable' => '1',
                'note' => 'Inspected and restocked',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertEquals(OrderStatus::Returned, $order->status);
        $this->assertEquals(OrderItemStatus::Returned, $order->items->first()->status);
        $this->assertEquals(ReturnRequestStatus::Approved, $order->returnRequests()->first()->status);
        $this->assertEquals(10, $product->fresh()->stock_quantity);
    }

    public function test_admin_can_decline_return_request(): void
    {
        Storage::fake('public');
        Notification::fake();

        $admin = User::factory()->admin()->create(['is_active' => true]);
        $user = User::factory()->customer()->create();
        $product = $this->createProduct();
        $order = $this->placeDeliveredOrder($user, $product, $admin);

        $this->actingAs($user)->post(route('returns.store'), $this->returnPayload($order, [
            'reason' => 'I no longer want this product.',
        ]));

        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order))
            ->post(route('admin.orders.return.decline', $order), [
                'note' => 'Item was used',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertEquals(OrderStatus::Delivered, $order->status);
        $this->assertEquals(ReturnRequestStatus::Declined, $order->returnRequests()->first()->status);
        $this->assertNull($order->pendingReturnRequest);
    }

    public function test_customer_can_return_partial_quantity(): void
    {
        Storage::fake('public');
        Notification::fake();

        $admin = User::factory()->admin()->create(['is_active' => true]);
        $user = User::factory()->customer()->create();
        $product = $this->createProduct(['stock_quantity' => 10]);
        $order = $this->placeDeliveredOrder($user, $product, $admin, 2);
        $item = $order->items->first();

        $this->actingAs($user)->post(route('returns.store'), $this->returnPayload($order, [
            'items' => [
                $item->id => [
                    'id' => $item->id,
                    'quantity' => 1,
                ],
            ],
            'reason' => 'One bottle arrived leaking in the box.',
        ]))->assertRedirect();

        $this->assertEquals(1, $order->fresh()->pendingReturnRequest->items()->first()->quantity);

        $this->actingAs($admin)->post(route('admin.orders.return', $order), [
            'resellable' => '1',
        ]);

        $order->refresh();
        $this->assertEquals(OrderStatus::Delivered, $order->status);
        $this->assertEquals(1, (int) $order->items->first()->quantity);
        $this->assertEquals(9, $product->fresh()->stock_quantity);
    }
}
