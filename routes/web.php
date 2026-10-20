<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'active'])->get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');
