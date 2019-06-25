<?php

Route::group(['prefix' => 'rakan', 'namespace' => 'TELstatic\\Rakan\\Controller'], function () {
    Route::post('callback/{gateway}', 'RakanController@saveFile')->name('rakan.callback');

    if (config('rakan.default.route')) {
        Route::group(['prefix' => 'file'], function () {
            //文件管理
            Route::get('index', 'FileController@getFiles');         //获取文件列表
            Route::get('policy', 'FileController@getPolicy');       //获取上传策略
            Route::post('create', 'FileController@createFolder');   //创建目录
            Route::post('check', 'FileController@checkFile');       //检查文件是否唯一
            Route::patch('visible', 'FileController@setVisible');   //设置文件可见性
            Route::post('paste', 'FileController@paste');           //文件目录复制粘贴
            Route::put('rename', 'FileController@rename');          //文件目录重命名
            Route::delete('batch', 'FileController@deleteFiles');   //删除文件目录
        });
    }
});
