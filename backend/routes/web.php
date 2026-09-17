<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('public/landing');
})->name('home');

require __DIR__.'/auth.php';
