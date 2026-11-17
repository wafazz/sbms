<?php

use Illuminate\Support\Facades\Route;

// Every non-API URL serves the Vue SPA shell. The router handles the page.
Route::get('/{any?}', fn () => view('app'))
    ->where('any', '^(?!api(/|$)).*')
    ->name('spa');
