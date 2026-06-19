<?php

use App\Http\Controllers\Payment\MidtransNotificationController;
use App\Http\Controllers\Payment\PaymentRedirectController;
use App\Models\Page;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::storefront.home')->name('home');
Route::livewire('/products', 'pages::storefront.catalog')->name('products.index');
Route::livewire('/product/{product:slug}', 'pages::storefront.product')->name('products.show');
Route::livewire('/cart', 'pages::storefront.cart')->name('cart.index');
Route::livewire('/wishlist', 'pages::storefront.wishlist')->name('wishlist.index');
Route::livewire('/checkout', 'pages::storefront.checkout')->name('checkout');
Route::livewire('/track', 'pages::storefront.track')->name('track');
Route::livewire('/order/{order:uuid}', 'pages::storefront.order-success')->name('checkout.success');

Route::post('/payments/midtrans/notification', MidtransNotificationController::class)
    ->name('payments.midtrans.notification');

Route::get('/payments/midtrans/finish', [PaymentRedirectController::class, 'finish'])
    ->name('payments.midtrans.finish');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard.index')->name('dashboard');
    Route::livewire('dashboard/orders', 'pages::dashboard.orders.index')->name('dashboard.orders.index');
    Route::livewire('dashboard/order/{order:order_number}', 'pages::dashboard.orders.show')->name('dashboard.orders.show');
    Route::livewire('dashboard/notifications', 'pages::dashboard.notifications')->name('dashboard.notifications.index');
    Route::livewire('dashboard/wishlist', 'pages::dashboard.wishlist')->name('dashboard.wishlist');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';

Route::livewire('/{page:slug}', 'pages::storefront.page')
    ->name('pages.show')
    ->where('page', '(?!('.implode('|', Page::RESERVED_SLUGS).')$)[a-z0-9][a-z0-9-]*');
