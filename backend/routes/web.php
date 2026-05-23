<?php

use Illuminate\Support\Facades\Route;

// Rota named 'login' necessária para o redirect de unauthenticated (SPA → frontend)
Route::get('/login', function () {
    return redirect(config('app.frontend_url') . '/login');
})->name('login');
