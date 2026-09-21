<?php

declare(strict_types=1);

use App\Http\Controllers\Api\NoteController;
use Illuminate\Support\Facades\Route;

/*
| Публичный CRUD заметок (без auth).
*/
Route::apiResource('notes', NoteController::class);
