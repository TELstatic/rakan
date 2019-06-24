<?php
/**
 * Created by PhpStorm.
 * User: TELstatic
 * Date: 2019-06-24
 * Time: 16:47
 */

namespace TELstatic\Rakan\Controller;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use TELstatic\Rakan\Models\Rakan as File;

/**
 * 文件管理
 * @desc 文件管理
 */
class FileController extends BaseController
{
    public $guard;

    public function __construct($guard)
    {
        $this->guard = $guard;
    }

    /**
     * 获取文件目录列表
     * @desc 获取文件目录列表
     */
    public function getFiles(Request $request)
    {
        return Auth::guard($this->gurad)->user()->getFiles($request->get('pid', 0), $request->get('per_page', 50), $request->get('keyword'));
    }

    /**
     * 设置文件权限
     * @desc
     */
    public function setVisible(Request $request)
    {
        return Auth::guard($this->guard)->user()->setACL($request->get('id'), $request->get('visible', 1));
    }


    /**
     * 重命名文件
     * @dec
     */
    public function rename(Request $request)
    {
        return Auth::guard($this->guard)->user()->rename($request->get('id'), $request->get('name'));
    }

    /**
     * 复制移动文件
     * @desc 复制移动文件
     */
    public function paste(Request $request)
    {
        switch ($request->get('action', 'copy')) {
            default:
            case 'copy':
                return Auth::guard($this->guard)->user()->copy($request->get('ids'), $request->get('folder_id'));
                break;
            case 'cut':
                return Auth::guard($this->guard)->user()->cut($request->get('ids'), $request->get('folder_id'));
                break;
        }
    }

    /**
     * 创建目录
     * @desc 创建目录
     * @param $request
     * @return object
     */
    public function createFolder(Request $request)
    {
        return Auth::guard($this->guard)->user()->createFolder($request->get('pid', 0), $request->get('name'));
    }

    /**
     * 删除文件
     * @desc 删除文件或目录
     * @param $request
     * @return object
     */
    public function deleteFiles(Request $request)
    {
        return Auth::guard($this->guard)->user()->deleteFiles($request->get('ids'));
    }

    /**
     * 检查文件唯一
     * @desc 确保文件唯一
     * @param $request
     * @return object
     */
    public function checkFile(Request $request)
    {
        return Auth::guard($this->guard)->user()->checkFile($request->get('path'));
    }

    /**
     * 获取上传策略
     * @desc 获取对象存储上传策略
     * @return object
     */
    public function getPolicy()
    {
        return Auth::guard($this->guard)->user()->getPolicy();
    }

}