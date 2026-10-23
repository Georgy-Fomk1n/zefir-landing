<?php

use Illuminate\Support\Facades\Route;

// api.зефир-и-пряник.рф: публичных страниц нет, корень отдаёт только служебный JSON
Route::get('/', fn () => response()->json(['service' => 'zefir-api']));
