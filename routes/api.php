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
*/


Route::controller(\App\Http\Controllers\Dtgraph\ApiController::class)->prefix('api')->group(function () {
    Route::get('sensor', 'sensor');
    Route::get('sensorname', 'sensorName');
    Route::get('reading', 'reading');
    Route::get('latest', 'latest');
    Route::get('add', 'add');
    Route::post('add', 'add');
});
