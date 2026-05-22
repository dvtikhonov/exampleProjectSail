<?php

use App\Http\Controllers\ObjectsSalesOutletsController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Http\Request;


Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

//Route::middleware('auth')->group(function () {
Route::middleware(['auth.passport'])->group(function () {
    Route::get('/objects-sales-outlets', [ObjectsSalesOutletsController::class, 'index'])
        ->name('objectsSalesOutlets.index');
    Route::get('/objects-sales-outlets-2', [ObjectsSalesOutletsController::class, 'darkIndex'])
        ->name('objectsSalesOutlets.darkIndex');
    Route::post('/objects-sales-outlets-2/export', [ObjectsSalesOutletsController::class, 'createExport'])
        ->name('objectsSalesOutlets.export.create');
    Route::get('/objects-sales-outlets-2/export/{uuid}', [ObjectsSalesOutletsController::class, 'exportStatus'])
        ->name('objectsSalesOutlets.export.status');
    Route::get('/objects-sales-outlets-2/export/{uuid}/download', [ObjectsSalesOutletsController::class, 'downloadExport'])
        ->name('objectsSalesOutlets.export.download');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
Route::middleware('auth')->get('/get-api-token', function (Request $request) {
    $token = $request->session()->get('passport_token');

    if (! $token) {
        $token = $request->user()->createTokenForSession();
        $request->session()->put('passport_token', $token);
    }

    return response()->json(['token' => $token]);
});

require __DIR__.'/auth.php';
