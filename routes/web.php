<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\DeploymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Guest routes handle authentication. Every deployment action is gated
| behind the "auth" middleware — this tool can deploy, wipe directories
| and restore databases, so it must never be reachable by a guest.
|
*/

// Authentication (guests only)
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.attempt');

    // Two-factor challenge (after a correct password, before full login).
    Route::get('two-factor-challenge', [AuthController::class, 'showChallenge'])->name('two-factor.challenge');
    Route::post('two-factor-challenge', [AuthController::class, 'challenge'])->name('two-factor.verify');
});

Route::post('logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Deployment dashboard (authenticated only)
Route::middleware('auth')->group(function () {
    Route::get('/', [DeploymentController::class, 'index'])->name('home');

    // Read-only downloads
    Route::get('download/{folder}', [DeploymentController::class, 'download'])->name('download');
    Route::get('download-db/{db_file}', [DeploymentController::class, 'downloadDb'])->name('downloadDb');

    // Two-factor settings
    Route::get('two-factor', [TwoFactorController::class, 'index'])->name('two-factor.index');
    Route::post('two-factor/enable', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
    Route::post('two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::post('two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');

    // Live deploy status (polled by the dashboard)
    Route::get('deploy-status', [DeploymentController::class, 'deployStatus'])->name('deploy.status');

    // Destructive actions — POST + CSRF only
    Route::post('deploy', [DeploymentController::class, 'deploy'])->name('deploy');
    Route::post('restore/{folder}', [DeploymentController::class, 'restore'])->name('restore');
    Route::post('restore-db/{db_file}', [DeploymentController::class, 'restoreDb'])->name('restoreDb');
});
