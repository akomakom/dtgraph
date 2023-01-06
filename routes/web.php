<?php

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

//Route::get('/', function () {
//    return view('welcome');
//});


Route::get('/', function () {
    return view('dtgraph/dynamic/main');
});

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| This route group applies the "web" middleware group to every route
| it contains. The "web" middleware group is defined in your HTTP
| kernel and includes session state, CSRF protection, and more.
|
*/

Route::group(['middleware' => ['web']], function () {
    //
});


//Route::group(['namespace' => 'Dtgraph', 'prefix' => 'api'], function() {
//
//    Route::resource('sensor', 'ApiController@sensor');
//    Route::resource('sensorname', 'ApiController@sensorName');
//    Route::resource('reading', 'ApiController@reading');
//    Route::resource('latest', 'ApiController@latest');
//    Route::resource('add', 'ApiController@add');
//});
