<?php

namespace TELstatic\Rakan\Traits;

use Illuminate\Support\Facades\Storage;
use TELstatic\Rakan\Models\Rakan as File;
use Hashids\Hashids;

/**
 * 文件扩展.
 */
trait Rakan
{
    public $prefix;
    public $module;
    public $gateway;

    public function __construct()
    {
        $this->module = config('rakan.default.module');
        $this->prefix = config('rakan.default.prefix');
        $this->gateway = config('rakan.default.gateway');
    }

    /**
     * 前缀.
     */
    public function prefix($prefix = 'rakan')
    {
        $this->prefix = $prefix;
        return $this;
    }

    /**
     * 模块.
     */
    public function module($module = 'default')
    {
        $this->module = $module;
        return $this;
    }

    /**
     * 网关.
     */
    public function gateway($gateway = 'oss')
    {
        $this->gateway = $gateway;
        return $this;
    }

    /**
     * 根目录.
     */
    protected function root()
    {
        $hashids = new Hashids(config('rakan.hashids.salt'), config('rakan.hashids.length'), config('rakan.hashids.alphabet'));

        return $this->prefix.'/'.$this->module.'/'.$hashids->encode($this->id);
    }

    /**
     * 获取Root目录.
     * 不存在则创建.
     */
    protected function getRootFolder()
    {
        $path = $this->root();

        $data = [
            'pid'       => 0,
            'path'      => $path,
            'name'      => 'Root',
            'module'    => $this->module,
            'gateway'   => $this->gateway,
            'host'      => config('rakan.gateways.'.$this->gateway.'.host'),
            'target_id' => $this->id,
            'type'      => 'folder',
            'sort'      => 255,
        ];

        $where = [];

        $where[] = [
            'name', 'Root',
        ];

        $where[] = [
            'module', $this->module,
        ];

        $where[] = [
            'target_id', $this->id,
        ];

        $where[] = [
            'gateway', $this->gateway
        ];

        $where[] = [
            'path', $path
        ];

        $files = File::where($where)->firstOrCreate($data, $data);

        return $files;
    }

    /**
     * 获取文件及目录.
     */
    public function getFiles($pid = 0, $per_page = 50)
    {
        $where = [];

        $where[] = [
            'module', $this->module,
        ];

        $where[] = [
            'target_id', $this->id,
        ];

        if (!$pid) {
            $where[] = [
                'pid', $pid,
            ];

            $parent = $this->getRootFolder();
        } else {
            $where[] = [
                'id', $pid,
            ];
            //todo should remove?
            $parent = File::where($where)->first();
        }

        $children = File::where(['pid' => $parent->id])->orderBy('sort', 'desc')->paginate($per_page);

        $data = [
            'parent'   => $parent,
            'children' => $children,
        ];

        return $data;
    }

    /**
     * 创建目录.
     */
    public function createFolder($pid, $name)
    {
        $parent = File::findOrFail($pid);

        $where = [];

        $where[] = [
            'pid', $pid,
        ];

        $where[] = [
            'type', 'folder',
        ];

        $where[] = [
            'name', $name,
        ];

        $where[] = [
            'gateway', $this->gateway
        ];

        $folder = File::where($where)->first();

        if ($folder) {
            return [
                'status' => 500,
                'msg'    => '目录已存在',
            ];
        }

        $data = [
            'pid'       => $parent->id,
            'path'      => $parent->path.'/'.$name,
            'name'      => $name,
            'module'    => $parent->module,
            'gateway'   => $this->gateway,
            'host'      => $parent->host,
            'target_id' => $this->id,
            'type'      => 'folder',
            'sort'      => 255,
        ];

        $bool = File::create($data);

        return [
            'status' => $bool ? 200 : 500,
            'msg'    => '目录创建'.$bool ? '成功' : '失败',
        ];
    }

    /**
     * 检查文件是否存在.
     */
    public function checkFile($path)
    {
        $bool = Storage::disk($this->gateway)->exists($path);

        return [
            'status' => $bool ? 500 : 200,
            'msg'    => $bool ? '文件已存在' : '',
        ];
    }

    /**
     * 删除本地文件记录.
     */
    public function deleteFiles($ids)
    {
        $where = [];

        $where [] = [
            'target_id', $this->id,
        ];

        $where[] = [
            'module', $this->module,
        ];

        $folders = File::where($where)->where(['type' => 'folder'])->whereIn('id', $ids)->pluck('path');
        $files = File::where($where)->where(['type' => 'file'])->whereIn('id', $ids)->pluck('path')->toArray();

        //检查目录下是否存在其他目录 或者 文件
        foreach ($folders as $folder) {
            $whereFolder = [];

            $whereFolder[] = [
                'path', 'like', $folder.'%',
            ];

            if (File::where($whereFolder)->count() > 1) {
                return [
                    'status' => 500,
                    'msg'    => '目录'.$folder.'不为空',
                ];
                break;
            }
        }

        $bool = $this->deleteObjects($files);

        if ($bool) {
            File::destroy($ids);

            return [
                'status' => 200,
                'msg'    => '文件删除成功',
            ];
        }

        return [
            'status' => 500,
            'msg'    => '文件删除失败',
        ];
    }

    /**
     * 删除文件.
     */
    public function deleteObjects($objects)
    {
        if (empty($objects)) {
            return true;
        }

        return Storage::disk($this->gateway)->delete($objects);
    }

    /**
     * 获取上传策略.
     */
    public function getPolicy()
    {
        return Storage::disk($this->gateway)->policy();
    }
}
