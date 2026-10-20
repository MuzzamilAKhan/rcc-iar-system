<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Admin\MasterDataDashboard;
use App\Livewire\Admin\ProductsIndex;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'active'])
    ->get('/dashboard', function () {
        return view('dashboard');
    })
    ->name('dashboard');

Route::middleware(['auth', 'active', 'can:manage-master-data'])->group(function () {
    Route::get('/admin/master-data', function () {
        return view('admin.master-data');
    })->name('admin.master-data');

    Route::get('/admin/products', function () {
        return view('admin.products');
    })->name('admin.products');

    Route::get('/admin/products/{product}', function (\App\Models\Product $product) {
        $product->load([
            'businessUnit',
            'registrations.country',
        ]);

        return view('admin.product-details', compact('product'));
    })->name('admin.products.show');

    Route::get('/admin/global-ra', function () {
        return view('admin.global-ra');
    })->name('admin.global-ra');


    Route::get('/admin/local-ra', function () {
        return view('admin.local-ra');
    })->name('admin.local-ra');

});
