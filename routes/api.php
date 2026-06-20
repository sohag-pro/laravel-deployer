<?php

use App\Http\Controllers\Api\DeployApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Token-authenticated endpoints (Laravel Sanctum) for controlling deploys
| remotely. Mint a token with: php artisan deployer:token "ci"
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    Route::post('/deploy', [DeployApiController::class, 'deploy'])->name('api.deploy');
    Route::get('/deploy/status', [DeployApiController::class, 'status'])->name('api.deploy.status');
});
