<?php

namespace TELstatic\Rakan\Controller;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;
use TELstatic\Rakan\Models\Rakan as File;

class RakanController extends BaseController
{
    /**
     * 保存文件.
     *
     * @param $request
     * @param $gateway
     *
     * @return object
     */
    public function saveFile(Request $request, $gateway, $bucket = null)
    {
        //非本地环境验证 合法性
        if (config('app.env') === 'production') {
            Storage::disk($gateway)->verify();
        }

        // BUG 用户A 伪造表单 上传文件 至 用户B 目录下
        $fileInfo = pathinfo($request->get('filename'));

        $folder = $this->getParentFolder($fileInfo['dirname'], $gateway);

        $file = $this->generateFile($request, $fileInfo['basename'], $gateway, $folder);

        return response()->json([
            'status' => $file ? 200 : 500,
        ]);
    }

    /**
     * 获取父级目录.
     *
     * @desc 不存在则使用递归创建目录
     */
    protected function getParentFolder($path, $gateway)
    {
        $where[] = [
            'path',
            $path,
        ];

        $where[] = [
            'gateway',
            $gateway,
        ];

        if ($folder = File::where($where)->first()) {
            return $folder;
        } else {
            if (!$path) {
                throw new \InvalidArgumentException('根目录不存在,请检查文件路径');
            }

            $parentFolder = $this->getParentFolder($this->getChildFolderPath($path), $gateway);

            if ($folder = $this->generateFolder($path, $gateway, $parentFolder)) {
                return $folder;
            }
        }
    }

    /**
     * 获取子目录路径.
     *
     * @desc
     */
    protected function getChildFolderPath($path)
    {
        $pathArr = explode('/', $path);

        array_pop($pathArr);

        $childFolder = implode('/', $pathArr);

        return $childFolder;
    }

    /**
     * 生成文件.
     *
     * @desc
     */
    protected function generateFile($request, $name, $gateway, $folder)
    {
        $data = [
            'path'      => $request->get('filename'),
            'size'      => $request->get('size', 1),
            'width'     => $request->get('width', 0),
            'height'    => $request->get('height', 0),
            'ext'       => $request->get('mimeType'),
            'name'      => $name,
            'gateway'   => $gateway,
            'host'      => $folder->host,
            'module'    => $folder->module,
            'target_id' => $folder->target_id,
            'pid'       => $folder->id,
            'sort'      => 0,
            'type'      => 'file',
            'visible'   => config('rakan.gateways.'.$gateway.'.acl', 1),
        ];

        $file = File::create($data);

        return $file;
    }

    /**
     * 生成目录.
     *
     * @desc
     */
    protected function generateFolder($path, $gateway, $folder)
    {
        $pathArr = explode('/', $path);

        $data = [
            'path'      => $path,
            'size'      => 0,
            'width'     => 0,
            'height'    => 0,
            'ext'       => null,
            'name'      => end($pathArr),
            'gateway'   => $gateway,
            'host'      => $folder->host,
            'module'    => $folder->module,
            'target_id' => $folder->target_id,
            'pid'       => $folder->id,
            'sort'      => 0,
            'type'      => 'folder',
        ];

        $file = File::create($data);

        return $file;
    }
}
