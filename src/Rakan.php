<?php

namespace TELstatic\Rakan;

use TELstatic\Rakan\Models\Storages;
use TELstatic\Rakan\Models\Files;
use TELstatic\Rakan\Models\Floders;

class Rakan
{
    /**
     * 初始化使用空间
     */
    public static function init($target_id, $space)
    {
        $storage = new Storages;

        $storage->target_id = $target_id;
        $storage->space = $space;

        return $storage->save();
    }

    /**
     * 获取文件列表
     */
    public static function getFiles($where = [], $order = [])
    {
        $file = new Files;

        $files = $file->where($where)->orderBy($order)->paginate(50);

        return $files;
    }

    /**
     * 创建文件
     */
    public function saveFiles()
    {

    }

    /**
     * 批量删除文件
     */
    public function deleteFiles()
    {

    }

    /**
     * 获取目录
     */
    public static function getFloders()
    {
        $categorys = Floders::with('chlidren')->first();

        return $categorys;
    }

    /**
     * 删除目录
     */
    public function deleteFloder($id)
    {
        $bool = Floders::deleted($id);
        return responseData($bool ? 200 : 500);
    }

    /**
     * 获取签名
     */
    public function getPolicy($target_id)
    {
        return newPolicy($target_id);
    }

}
