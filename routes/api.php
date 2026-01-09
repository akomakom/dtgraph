<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
| Note: These routes automatically get the /api prefix from RouteServiceProvider
|
*/

Route::controller(\App\Http\Controllers\Dtgraph\ApiController::class)->group(function () {
    Route::get('sensor', 'sensor');
    Route::get('sensorname', 'sensorName');
    Route::get('reading/{sensor}', 'reading');
    Route::get('latest', 'latest');
    Route::get('health/{sensor}', 'healthCheck');
    Route::get('health', 'healthCheck');
    Route::get('add/{sensor}', 'add');
    Route::post('add/{sensor}', 'add');
});
