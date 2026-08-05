<?php

use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BarcodeLookupController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::delete('products/bulk', [ProductController::class, 'bulkDestroy'])->name('products.bulk-destroy');
Route::resource('products', ProductController::class)->except(['show']);

Route::delete('categories/bulk', [CategoryController::class, 'bulkDestroy'])->name('categories.bulk-destroy');
Route::resource('categories', CategoryController::class)->except(['show']);

Route::delete('brands/bulk', [BrandController::class, 'bulkDestroy'])->name('brands.bulk-destroy');
Route::resource('brands', BrandController::class)->except(['show']);

Route::delete('coupons/bulk', [CouponController::class, 'bulkDestroy'])->name('coupons.bulk-destroy');
Route::resource('coupons', CouponController::class)->except(['show']);

Route::delete('banners/bulk', [BannerController::class, 'bulkDestroy'])->name('banners.bulk-destroy');
Route::resource('banners', BannerController::class)->except(['show']);

Route::get('attributes', [AttributeController::class, 'index'])->name('attributes.index');
Route::post('attributes', [AttributeController::class, 'store'])->name('attributes.store');
Route::post('attributes/{attribute}/values', [AttributeController::class, 'storeValue'])->name('attributes.values.store');
Route::delete('attributes/bulk', [AttributeController::class, 'bulkDestroy'])->name('attributes.bulk-destroy');
Route::delete('attributes/{attribute}', [AttributeController::class, 'destroy'])->name('attributes.destroy');

Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
Route::post('inventory/unlock', [InventoryController::class, 'unlock'])->name('inventory.unlock');
Route::post('inventory/lock', [InventoryController::class, 'lock'])->name('inventory.lock');
Route::post('inventory/adjust', [InventoryController::class, 'adjust'])->middleware('inventory.unlocked')->name('inventory.adjust');
Route::match(['get', 'post'], 'inventory/scan', [InventoryController::class, 'scan'])->name('inventory.scan');

Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
Route::post('orders/{order}/approve', [OrderController::class, 'approve'])->name('orders.approve');
Route::post('orders/{order}/reject', [OrderController::class, 'reject'])->name('orders.reject');
Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
Route::post('orders/{order}/notes', [OrderController::class, 'addNote'])->name('orders.notes');
Route::post('orders/{order}/return', [OrderController::class, 'confirmReturn'])->name('orders.return');
Route::post('orders/{order}/mark-paid', [OrderController::class, 'markPaid'])->name('orders.mark-paid');
Route::get('orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
Route::get('orders/{order}/packing-slip', [OrderController::class, 'packingSlip'])->name('orders.packing-slip');

Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('reports/orders.csv', [ReportController::class, 'exportOrders'])->name('reports.orders.csv');

Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

Route::get('barcodes', [BarcodeLookupController::class, 'index'])->name('barcodes.index');
Route::get('barcodes/labels', [BarcodeLookupController::class, 'labels'])->name('barcodes.labels');
