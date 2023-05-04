<?php

use App\Http\Controllers\DeploymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
 */

Route::get('/', [DeploymentController::class, 'index']);
Route::get('download/{folder}', [DeploymentController::class, 'download'])->name('download');
Route::get('restore/{folder}', [DeploymentController::class, 'restore'])->name('restore');
Route::get('download-db/{db_file}', [DeploymentController::class, 'downloadDb'])->name('downloadDb');
Route::get('restore-db/{db_file}', [DeploymentController::class, 'restoreDb'])->name('restoreDb');
Route::get('deploy', [DeploymentController::class, 'deploy'])->name('deploy');
