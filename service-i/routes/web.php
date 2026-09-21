<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
| Vue SPA: catch-all отдаёт blade с Vite-бандлом.
| API живёт в routes/api.php (/api/notes).
*/
Route::view('/{any?}', 'spa')
    ->where('any', '.*')
    ->name('spa');
