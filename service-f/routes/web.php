<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShortLinkRedirectController;
use Illuminate\Support\Facades\Route;

// Публичная заглушка Laravel (не используется в основном сценарии).
Route::get('/', function () {
    return view('welcome');
});

// Breeze dashboard → Filament admin.
Route::redirect('/dashboard', '/admin')->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Публичный редирект: должен быть последним (catch-all по коду [A-Za-z0-9]{4,12}).
Route::get('/{code}', ShortLinkRedirectController::class)
    ->where('code', '[A-Za-z0-9]{4,12}')
    ->name('shortLinks.redirect');
