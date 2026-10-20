<?php

declare(strict_types=1);

use App\Http\Controllers\Ofrep\EvaluateFlagController;
use App\Http\Controllers\Ofrep\EvaluateFlagsController;
use Illuminate\Support\Facades\Route;

// OFREP fixes these paths (ADR-0001). They take none of the v1 group's middleware, and never
// conditional, preconditions or idempotent: the bulk route answers If-None-Match itself (ADR-0003).
Route::post('/evaluate/flags', EvaluateFlagsController::class)->name('ofrep.flags');
Route::post('/evaluate/flags/{key}', EvaluateFlagController::class)->name('ofrep.flag');
