<?php

use Illuminate\Support\Facades\Route;

/*
| REST API — versioned under /api/v1 (see routes/api/v1.php and docs/api/openapi.yaml)
*/
Route::prefix('v1')->name('api.v1.')->group(base_path('routes/api/v1.php'));
