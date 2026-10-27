<?php

namespace Tests\Feature;

use App\Enums\ContentSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Models\DeliveryRegion;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_fix_the_delivery_choice_and_the_charge_updates_the_total(): void
    {
        [$admin, $order, $beirut, $north] = $this->orderWithRegions();
        $this->assertSame('23.00', $order->total);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Delivery choice')
            ->assertSee('Delivery charge');

        $this->post(route('admin.orders.delivery', $order), [
            'delivery_region_id' => $north->id,
            'delivery_fee' => '6.00',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame($north->id, (int) $order->delivery_region_id);
        $this->assertSame('6.00', $order->delivery_fee);
        $this->assertSame('26.00', $order->total);
        $this->assertTrue($order->notes()->where('body', 'like', '%Delivery choice changed%')->exists());
    }

    public function test_admin_can_change_only_the_charge_to_a_custom_amount(): void
    {
        [$admin, $order, $beirut] = $this->orderWithRegions();

        $this->actingAs($admin)->post(route('admin.orders.delivery', $order), [
            'delivery_region_id' => $beirut->id,
            'delivery_fee' => '1.50',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame($beirut->id, (int) $order->delivery_region_id);
        $this->assertSame('1.50', $order->delivery_fee);
        $this->assertSame('21.50', $order->total);
    }

    public function test_delivery_cannot_change_on_a_paid_or_closed_order(): void
    {
        [$admin, $order, $beirut, $north] = $this->orderWithRegions();

        $order->update(['payment_status' => PaymentStatus::Paid]);
        $this->actingAs($admin)->post(route('admin.orders.delivery', $order), [
            'delivery_region_id' => $north->id,
            'delivery_fee' => '6.00',
        ])->assertSessionHasErrors('delivery');
        $this->assertSame('3.00', $order->fresh()->delivery_fee);

        $order->update(['payment_status' => PaymentStatus::Pending, 'status' => OrderStatus::Delivered]);
        $this->post(route('admin.orders.delivery', $order), [
            'delivery_region_id' => $north->id,
            'delivery_fee' => '6.00',
        ])->assertSessionHasErrors('delivery');
        $this->assertSame('3.00', $order->fresh()->delivery_fee);
    }

    public function test_higher_charge_asks_customer_to_confirm_and_holds_approval(): void
    {
        [$admin, $order, , $north] = $this->orderWithRegions();
        $customer = $order->user;

        $this->actingAs($admin)->post(route('admin.orders.delivery', $order), [
            'delivery_region_id' => $north->id,
            'delivery_fee' => '6.00',
        ])->assertRedirect();

        $order->refresh();
        $this->assertTrue($order->hasPendingDeliveryConfirmation());
        $this->assertSame('3.00', $order->delivery_fee_before);
        $this->assertEqualsWithDelta(2 * 3600, now()->diffInSeconds($order->delivery_confirmation_due_at, false), 5);

        $notification = $customer->notifications()
            ->where('type', \App\Notifications\OrderDeliveryConfirmationNotification::class)
            ->first();
        $this->assertNotNull($notification);
        $this->assertTrue((bool) $notification->data['delivery_confirmation']);

        $this->actingAs($customer)
            ->get(route('account.notifications.index'))
            ->assertOk()
            ->assertSee(__('storefront.delivery_confirm_button'));
        $this->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee(__('storefront.delivery_confirm_heading'))
            ->assertSee(__('storefront.delivery_decline_button'));

        $this->actingAs($admin)
            ->post(route('admin.orders.approve', $order))
            ->assertSessionHasErrors('status');
        $this->assertSame(OrderStatus::PendingApproval, $order->fresh()->status);
    }

    public function test_customer_confirming_releases_the_hold(): void
    {
        [$admin, $order, , $north] = $this->orderWithRegions();
        $this->actingAs($admin)->post(route('admin.orders.delivery', $order), [
            'delivery_region_id' => $north->id,
            'delivery_fee' => '6.00',
        ]);

        $this->actingAs($order->user)
            ->post(route('account.orders.delivery.confirm', $order))
            ->assertRedirect(route('account.orders.show', $order));

        $order->refresh();
        $this->assertFalse($order->hasPendingDeliveryConfirmation());
        $this->assertSame('confirmed', $order->delivery_confirmation_outcome);

        $adminNote = $admin->notifications()
            ->where('type', \App\Notifications\OrderDeliveryConfirmationNotification::class)
            ->first();
        $this->assertNotNull($adminNote, 'Admin should be notified when the customer confirms.');
        $this->assertSame('Customer confirmed the delivery charge', $adminNote->data['title']);
        $this->assertSame(route('admin.orders.show', $order), $adminNote->data['url']);
        $this->assertTrue((bool) $adminNote->data['for_admin']);

        $this->actingAs($admin)->post(route('admin.orders.approve', $order))->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);
    }

    public function test_order_carries_on_automatically_after_two_hours(): void
    {
        [$admin, $order, , $north] = $this->orderWithRegions();
        $this->actingAs($admin)->post(route('admin.orders.delivery', $order), [
            'delivery_region_id' => $north->id,
            'delivery_fee' => '6.00',
        ]);

        $this->artisan('orders:expire-delivery-confirmations')->assertSuccessful();
        $this->assertTrue($order->fresh()->hasPendingDeliveryConfirmation());

        $this->travel(2)->hours();
        $this->travel(1)->minutes();
        $this->artisan('orders:expire-delivery-confirmations')->assertSuccessful();

        $order->refresh();
        $this->assertFalse($order->hasPendingDeliveryConfirmation());
        $this->assertSame('auto_confirmed', $order->delivery_confirmation_outcome);
        $this->assertSame('26.00', $order->total);

        $this->actingAs($admin)->post(route('admin.orders.approve', $order))->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);
    }

    public function test_customer_can_decline_the_new_charge_and_cancel(): void
    {
        [$admin, $order, , $north] = $this->orderWithRegions();
        $this->actingAs($admin)->post(route('admin.orders.delivery', $order), [
            'delivery_region_id' => $north->id,
            'delivery_fee' => '6.00',
        ]);

        $this->actingAs($order->user)
            ->post(route('account.orders.delivery.decline', $order))
            ->assertRedirect(route('account.orders.index'));

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame('declined', $order->delivery_confirmation_outcome);
    }

    public function test_lower_charge_needs_no_confirmation_and_admin_can_mark_confirmed(): void
    {
        [$admin, $order, $beirut, $north] = $this->orderWithRegions();

        $this->actingAs($admin)->post(route('admin.orders.delivery', $order), [
            'delivery_region_id' => $beirut->id,
            'delivery_fee' => '1.00',
        ]);
        $this->assertFalse($order->fresh()->hasPendingDeliveryConfirmation());

        $this->post(route('admin.orders.delivery', $order), [
            'delivery_region_id' => $north->id,
            'delivery_fee' => '6.00',
        ]);
        $this->assertTrue($order->fresh()->hasPendingDeliveryConfirmation());

        $this->post(route('admin.orders.delivery.confirmed', $order))->assertRedirect();
        $this->assertSame('admin_confirmed', $order->fresh()->delivery_confirmation_outcome);
    }

    public function test_customers_cannot_edit_delivery(): void
    {
        [, $order, , $north] = $this->orderWithRegions();
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->post(route('admin.orders.delivery', $order), [
            'delivery_region_id' => $north->id,
            'delivery_fee' => '0',
        ])->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Order, 2: DeliveryRegion, 3: DeliveryRegion}
     */
    protected function orderWithRegions(): array
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->customer()->create();
        $beirut = DeliveryRegion::query()->create(['name' => 'Beirut', 'code' => 'BEI', 'fee' => 3, 'is_active' => true]);
        $north = DeliveryRegion::query()->create(['name' => 'North', 'code' => 'NTH', 'fee' => 6, 'is_active' => true]);
        $product = Product::query()->create([
            'name' => 'Delivery Serum',
            'slug' => 'delivery-serum',
            'sku' => 'SKU-DEL',
            'barcode' => 'BC-DEL',
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
        ]);

        $this->actingAs($user);
        app(CartService::class)->add($product, 1);
        $order = app(CheckoutService::class)->placeOrder($user, [
            'payment_method' => PaymentMethod::CashOnDelivery->value,
            'delivery_region_id' => $beirut->id,
            'idempotency_token' => 'delivery-edit-1',
            'shipping' => ['full_name' => $user->name, 'phone' => '010', 'line1' => 'A', 'city' => 'Beirut'],
        ]);

        return [$admin, $order, $beirut, $north];
    }
}
