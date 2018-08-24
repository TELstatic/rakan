<?php

Route::group(['prefix' => 'rakan', 'namespace' => 'TELstatic\\Rakan\\Controller'], function () {
    Route::post('callback', 'RakanController@saveFile')->name('rakan.callback');
});