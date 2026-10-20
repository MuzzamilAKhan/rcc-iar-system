<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Admin\MasterDataDashboard;

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

});
