<?php

namespace TELstatic\Rakan\Traits;

use Illuminate\Support\Facades\Log;
use TELstatic\Rakan\Models\Files;
use OSS\OssClient;
use OSS\Core\OssException;

/**
 */
trait Rakan
{
    public function __construct()
    {
        if (empty($this->prefix)) {
            throw new \Exception('前缀必须');
        }
        if (empty($this->module)) {
            throw new \Exception('模块名必须');
        }

        $this->per_page = !empty($this->per_page) ? $this->per_page:50;

        if (empty($this->max_depth)) {
            throw new \Exception('目录深度必须');
        }
        if (empty($this->max_width)) {
            throw new \Exception('目录宽度必须');
        }
        if (empty($this->expire)) {
            throw new \Exception('策略时效必须');
        }
    }

    /**
     * 模块
     */
    public function module($name)
    {
        $this->module = $name;
    }

    /**
     * 根目录
     */
    public function root()
    {
        return $this->prefix . '/' . hashid_encode($this->id);
    }

    /**
     * 创建Root目录
     */
    protected function createRootFolder()
    {
        $path = $this->root();

        $data = [
            'pid'       => 0,
            'path'      => $path,
            'name'      => 'Root',
            'module'    => $this->module,
            'target_id' => $this->id,
            'type'      => 'folder',
            'sort'      => 255
        ];

        $where = [];

        $where[] = [
            'pid', 0
        ];

        $where[] = [
            'name', 'Root',
        ];

        $where[] = [
            'module', $this->module,
        ];

        $where[] = [
            'target_id', $this->id,
        ];

        $files = Files::firstOrCreate($where, $data);

        return $files;
    }

    /**
     * 获取文件及目录
     */
    public function getFiles($pid = 0)
    {
        $where = [];

        $where[] = [
            'module', $this->module
        ];

        $where[] = [
            'target_id', $this->id
        ];

        if ($pid == 0) {
            $where[] = [
                'pid', $pid
            ];

            $parent = $this->createRootFolder();
        } else {
            $where[] = [
                '_id', $pid
            ];

            $parent = Files::where($where)->first();
        }

        $children = Files::where(['pid' => $parent->_id])->orderBy('sort', 'desc')->paginate($this->per_page);

        $data = [
            'parent'   => $parent,
            'children' => $children
        ];

        return $data;
    }

    /**
     * 创建目录
     */
    public function createFolder($pid, $name)
    {
        $parent = Files::findOrFail($pid);

        $where = [];

        $where[] = [
            'pid', $pid
        ];

        $where[] = [
            'type', 'folder'
        ];

        $childCount = Files::where($where)->count();

        if ($childCount >= $this->max_folder) {
            return [
                'status' => 500,
                'msg'    => '目录超出限制'
            ];
        }

        $where[] = [
            'name', $name,
        ];

        $folder = Files::where($where)->first();

        if ($folder) {
            return [
                'status' => 500,
                'msg'    => '目录已存在'
            ];
        }

        $data = [
            'pid'       => $parent->_id,
            'path'      => $parent->path . '/' . $name,
            'name'      => $name,
            'module'    => $this->module,
            'target_id' => $this->id,
            'type'      => 'folder',
            'sort'      => 255
        ];

        $bool = Files::create($data);

        if ($bool) {
            return [
                'status' => 200,
                'msg'    => '目录创建成功'
            ];
        }
        return [
            'status' => 500,
            'msg'    => '目录创建失败'
        ];
    }

    /**
     * 检查文件是否存在
     */
    public function checkFile($path)
    {
        $bool = $this->checkObject($path);

        if ($bool) {
            return [
                'status' => 500,
                'msg'    => '文件已存在'
            ];
        }

        return [
            'status' => 200,
            'msg'    => ''
        ];
    }

    /**
     * 检查文件唯一性
     */
    protected function checkObject($object)
    {
        $accessKeyId = config('rakan.oss.access_key');
        $accessKeySecret = config('rakan.oss.secret_key');
        $endpoint = config('rakan.oss.endpoint');
        $bucket = config('rakan.oss.bucket');

        try {
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            $exist = $ossClient->doesObjectExist($bucket, $object);
        } catch (OssException $e) {
            Log::error($e->getMessage());
            return false;
        }

        return $exist;
    }

    /**
     * 删除本地文件记录
     */
    public function deleteFiles($ids)
    {
        $where = [];

        $where [] = [
            'target_id' => $this->id
        ];

        $where[] = [
            'module', $this->module
        ];

        $folders = Files::where($where)->where(['type' => 'folder'])->whereIn('_id', $ids)->pluck('path');
        $files = Files::where($where)->where(['type' => 'file'])->whereIn('_id', $ids)->pluck('path')->toArray();

        //检查目录下是否存在其他目录 或者 文件
        foreach ($folders as $folder) {
            $whereFolder = [];

            $whereFolder[] = [
                'path', 'like', $folder . '%'
            ];

            if (Files::where($whereFolder)->count() > 1) {
                return [
                    'status' => 500,
                    'msg'    => '目录' . $folder . '不为空'
                ];
                break;
            }
        }

        $bool = $this->deleteObjects($files);

        if ($bool) {
            Files::destroy($ids);

            return [
                'status' => 200,
                'msg'    => '文件删除成功'
            ];
        }

        return [
            'status' => 500,
            'msg'    => '文件删除失败'
        ];
    }

    /**
     * 删除Oss文件
     */
    protected function deleteObjects($objects)
    {
        if (empty($objects)) {
            return true;
        }

        $accessKeyId = config('rakan.oss.access_key');
        $accessKeySecret = config('rakan.oss.secret_key');
        $endpoint = config('rakan.oss.endpoint');
        $bucket = config('rakan.oss.bucket');

        try {
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            $ossClient->deleteObjects($bucket, $objects);
        } catch (OssException $e) {
            Log::error($e->getMessage());
            return false;
        }

        return true;
    }


    /**
     * 获取上传策略
     */
    public function getPolicy()
    {
        $accessKeyId = config('rakan.oss.access_key');
        $accessKeySecret = config('rakan.oss.secret_key');
        $host = config('rakan.oss.host');
        $callbackUrl = config('website.ali.oss.callback');

        $callback_param = [
            'callbackUrl'      => $callbackUrl,
            'callbackBody'     => 'filename=${object}&size=${size}&mimeType=${mimeType}&height=${imageInfo.height}&width=${imageInfo.width}',
            'callbackBodyType' => "application/x-www-form-urlencoded",
        ];
        $callback_string = json_encode($callback_param);
        $base64_callback_body = base64_encode($callback_string);

        $now = time();
        $expire = $this->expire;

        $end = $now + $expire;
        $expiration = self::gmt_iso8601($end);

        $condition = [
            0 => 'content-length-range',
            1 => 0,
            2 => 1048576000,
        ];
        $conditions[] = $condition;

        $arr = [
            'expiration' => $expiration,
            'conditions' => $conditions,
        ];

        $policy = json_encode($arr);
        $base64_policy = base64_encode($policy);
        $string_to_sign = $base64_policy;
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $accessKeySecret, true));

        $response = [];
        $response['accessid'] = $accessKeyId;
        $response['host'] = $host;
        $response['policy'] = $base64_policy;
        $response['signature'] = $signature;
        $response['expire'] = $expire;
        $response['callback'] = $base64_callback_body;

        return $response;
    }

    private function gmt_iso8601($time)
    {
        $dtStr = date("c", $time);
        $mydatetime = new \DateTime($dtStr);
        $expiration = $mydatetime->format(\DateTime::ISO8601);
        $pos = strpos($expiration, '+');
        $expiration = substr($expiration, 0, $pos);

        return $expiration . "Z";
    }
}