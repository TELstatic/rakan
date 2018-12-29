<?php

Route::group(['prefix' => 'rakan', 'namespace' => 'TELstatic\\Rakan\\Controller'], function () {
    Route::post('callback/{gateway}', 'RakanController@saveFile')->name('rakan.callback');
});
