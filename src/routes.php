<?php

Route::group(['prefix' => 'rakan', 'namespace' => 'TELstatic\\Rakan\\Controller'], function () {
    Route::prefix('file')->group(function () {
        Route::get('index', 'FileController@index');
        Route::post('/', 'FileController@store');
    });
    Route::prefix('floder')->group(function () {
        Route::get('index', 'FloderController@index');
    });
    Route::prefix('storage')->group(function () {
        Route::get('index', 'StorageController@index');
    });
    Route::prefix('oss')->group(function () {
        Route::get('index', 'OssController@index');
    });
});