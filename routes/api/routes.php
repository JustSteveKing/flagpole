<?php

declare(strict_types=1);

use App\Flags\SdkKeys;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('consumes:application/json')->group(base_path(
    path: 'routes/api/v1.php',
));

Route::prefix('ofrep/v1')
    ->middleware(['consumes:application/json', 'auth:sanctum', 'abilities:' . SdkKeys::ABILITY, 'sdk-key', 'throttle:ofrep'])
    ->group(base_path(
        path: 'routes/api/ofrep.php',
    ));
