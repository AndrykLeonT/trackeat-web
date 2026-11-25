<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\ActiveTrackEatUser;
use App\Http\Middleware\RequireRole;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', ActiveTrackEatUser::class])->group(function () {
    Route::get('perfil', [ProfileController::class, 'show'])->name('perfil');
    Route::patch('perfil', [ProfileController::class, 'update'])->middleware('throttle:10,1')->name('perfil.update');
    Route::put('perfil/contrasena', [ProfileController::class, 'changePassword'])->middleware('throttle:5,1')->name('perfil.password');

    Route::prefix('admin')->name('admin.')->middleware(RequireRole::class.':administrador')->group(function () {
        Route::get('/', DashboardController::class)->name('panel');
        Route::get('usuarios', [UserController::class, 'index'])->name('usuarios');
        Route::get('usuarios/crear', [UserController::class, 'create'])->name('usuarios.create');
        Route::get('usuarios/{user}/editar', [UserController::class, 'edit'])->where('user', '[0-9]{1,18}')->name('usuarios.edit');
        Route::post('usuarios', [UserController::class, 'store'])->middleware('throttle:30,1')->name('usuarios.store');
        Route::patch('usuarios/{user}', [UserController::class, 'update'])->where('user', '[0-9]{1,18}')->middleware('throttle:30,1')->name('usuarios.update');
        Route::delete('usuarios/{user}', [UserController::class, 'destroy'])->where('user', '[0-9]{1,18}')->middleware('throttle:30,1')->name('usuarios.destroy');
    });
});

Route::get('403', fn () => abort(403))->name('forbidden');
