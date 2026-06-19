<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');

    Route::livewire('orders', 'pages::admin.orders.index')->name('orders.index');
    Route::livewire('orders/{order:order_number}', 'pages::admin.orders.show')->name('orders.show');

    Route::livewire('products', 'pages::admin.products.index')->name('products.index');
    Route::livewire('products/create', 'pages::admin.products.form')->name('products.create');
    Route::livewire('products/{product}/edit', 'pages::admin.products.form')->name('products.edit');

    Route::livewire('categories', 'pages::admin.categories.index')->name('categories.index');
    Route::livewire('vouchers', 'pages::admin.vouchers.index')->name('vouchers.index');
    Route::livewire('pages', 'pages::admin.pages.index')->name('pages.index');
    Route::livewire('slides', 'pages::admin.slides.index')->name('slides.index');
    Route::livewire('settings', 'pages::admin.settings')->name('settings');

    Route::livewire('users', 'pages::admin.users.index')->name('users.index');

    Route::livewire('notifications', 'pages::admin.notifications.index')->name('notifications.index');
});
