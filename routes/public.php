<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/catalog', [PageController::class, 'catalog'])->name('catalog');
Route::get('/product/{slug}', [PageController::class, 'product'])->name('product');
Route::get('/delivery', [PageController::class, 'delivery'])->name('delivery');
Route::get('/checkout', [PageController::class, 'checkout'])->name('checkout');
Route::get('/offline', [PageController::class, 'offline'])->name('offline');
Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);
