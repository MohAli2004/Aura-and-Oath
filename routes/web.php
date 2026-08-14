<?php

use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\WhishPaymentController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/shop', ShopController::class)->name('shop');
Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
Route::get('/search', SearchController::class)->name('search');
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
Route::post('/cart/batch', [CartController::class, 'storeBatch'])->name('cart.batch');
Route::patch('/cart/{item}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{item}', [CartController::class, 'destroy'])->name('cart.destroy');

Route::get('/track-order', [OrderController::class, 'track'])->name('orders.track');
Route::get('/returns', [ReturnController::class, 'index'])->name('returns.index');
Route::post('/returns', [ReturnController::class, 'store'])->name('returns.store');
Route::post('/orders/cancel', [OrderController::class, 'cancelLookup'])->name('orders.cancel');

Route::get('/about', [PageController::class, 'about'])->name('pages.about');
Route::get('/contact', [PageController::class, 'contact'])->name('pages.contact');
Route::post('/contact', [PageController::class, 'contactSubmit'])->name('pages.contact.submit');
Route::get('/faq', [PageController::class, 'faq'])->name('pages.faq');
Route::get('/pages/{slug}', [PageController::class, 'show'])->name('pages.show');

Route::post('/newsletter', [NewsletterController::class, 'store'])->name('newsletter.store');

Route::match(['get', 'post'], '/payments/whish/callback/success', [WhishPaymentController::class, 'callbackSuccess'])
    ->name('payments.whish.callback.success');
Route::match(['get', 'post'], '/payments/whish/callback/failure', [WhishPaymentController::class, 'callbackFailure'])
    ->name('payments.whish.callback.failure');

Route::middleware('auth')->group(function () {
    Route::get('/payments/whish/return/success/{order}', [WhishPaymentController::class, 'returnSuccess'])
        ->name('payments.whish.return.success');
    Route::get('/payments/whish/return/failure/{order}', [WhishPaymentController::class, 'returnFailure'])
        ->name('payments.whish.return.failure');
    Route::post('/payments/whish/continue/{order}', [WhishPaymentController::class, 'continue'])
        ->name('payments.whish.continue');

    Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::post('/checkout/coupon', [CheckoutController::class, 'applyCoupon'])->name('checkout.coupon');

    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist', [WishlistController::class, 'store'])->name('wishlist.store');
    Route::delete('/wishlist/{item}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

    Route::get('/account', [AccountController::class, 'index'])->name('account.index');
    Route::put('/account', [AccountController::class, 'update'])->name('account.update');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::get('/account/addresses', [AccountController::class, 'addresses'])->name('account.addresses');
    Route::post('/account/addresses', [AccountController::class, 'storeAddress'])->name('account.addresses.store');
    Route::delete('/account/addresses/{address}', [AccountController::class, 'destroyAddress'])->name('account.addresses.destroy');

    Route::get('/account/notifications', [NotificationController::class, 'index'])->name('account.notifications.index');
    Route::get('/account/notifications/feed', [NotificationController::class, 'feed'])->name('account.notifications.feed');
    Route::post('/account/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('account.notifications.read-all');
    Route::post('/account/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('account.notifications.read');

    Route::get('/push/status', [PushSubscriptionController::class, 'status'])->name('push.status');
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.store');
    Route::delete('/push/subscribe', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');

    Route::get('/account/orders', [OrderController::class, 'index'])->name('account.orders.index');
    Route::get('/account/orders/{order}', [OrderController::class, 'show'])->name('account.orders.show');
    Route::post('/account/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('account.orders.cancel');
});
