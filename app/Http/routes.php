<?php

/*
|--------------------------------------------------------------------------
| Routes File
|--------------------------------------------------------------------------
|
| Here is where you will register all of the routes in an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

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


Route::group(['namespace' => 'Dtgraph', 'prefix' => 'api'], function() {

    Route::resource('sensor', 'ApiController@sensor');
    Route::resource('sensorname', 'ApiController@sensorName');
    Route::resource('reading', 'ApiController@reading');
    Route::resource('latest', 'ApiController@latest');
    Route::resource('add', 'ApiController@add');
});