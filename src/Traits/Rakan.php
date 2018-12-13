<?php

namespace TELstatic\Rakan\Traits;

use OSS\OssClient;
use OSS\Core\OssException;
use TELstatic\Rakan\Models\Rakan as File;
use Illuminate\Support\Facades\Log;

/**
 * 文件扩展.
 */
trait Rakan
{
    public $client;
    public $prefix;
    public $module;
    public $expire;

    public $accessKey;
    public $accessSecret;
    public $endpoint;
    public $bucket;

    public function __construct()
    {
        $this->accessKey = config('rakan.oss.access_key');
        $this->accessSecret = config('rakan.oss.secret_key');
        $this->endpoint = config('rakan.oss.endpoint');
        $this->bucket = config('rakan.oss.bucket');

        $this->module = 'default';
        $this->prefix = 'rakan';
        $this->expire = '120';

        try {
            $this->client = new OssClient($this->accessKey, $this->accessSecret, $this->endpoint);
        } catch (OssException $exception) {

            Log::error($exception->getMessage());

            exit(500);
        }
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
     * 有效时间.
     * 默认 120s.
     */
    public function expire($seconds = 0)
    {
        if ($seconds) {
            $this->expire = $seconds;
        }

        $this->expire = config('rakan.oss.expire', 120);
        return $this;
    }

    /**
     * 根目录.
     */
    public function root()
    {
        return $this->prefix.'/'.$this->module.'/'.hashid_encode($this->id);
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

        $files = File::where($where)->firstOrCreate(['pid' => 0], $data);

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
        $bool = $this->checkObject($path);

        return [
            'status' => $bool ? 500 : 200,
            'msg'    => $bool ? '文件已存在' : '',
        ];
    }

    /**
     * 检查文件唯一性.
     */
    protected function checkObject($object)
    {
        $exist = $this->client->doesObjectExist($this->bucket, $object);

        return $exist;
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
     * 删除Oss文件.
     */
    protected function deleteObjects($objects)
    {
        if (empty($objects)) {
            return true;
        }

        $bool = $this->client->deleteObjects($this->bucket, $objects);

        return $bool;
    }

    /**
     * 获取上传策略.
     */
    public function getPolicy()
    {
        $callback_param = [
            'callbackUrl'      => route('rakan.callback'),
            'callbackBody'     => 'filename=${object}&size=${size}&mimeType=${mimeType}&height=${imageInfo.height}&width=${imageInfo.width}',
            'callbackBodyType' => 'application/x-www-form-urlencoded',
        ];

        $callback_string = json_encode($callback_param);
        $base64_callback_body = base64_encode($callback_string);

        $now = time();
        $expire = $this->expire;

        $end = $now + $expire;
        $expiration = self::gmt_iso8601($end);

        $condition = [
            'content-length-range',
            0,
            1048576000,
        ];
        $conditions[] = $condition;

        $start = [
            'starts-with',
            '$key',
            $this->root(),
        ];

        $conditions[] = $start;

        $arr = [
            'expiration' => $expiration,
            'conditions' => $conditions,
        ];

        $policy = json_encode($arr);
        $base64_policy = base64_encode($policy);
        $string_to_sign = $base64_policy;
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $this->accessSecret, true));

        $response = [];
        $response['accessid'] = $this->accessKey;
        $response['host'] = config('rakan.oss.host');
        $response['policy'] = $base64_policy;
        $response['signature'] = $signature;
        $response['expire'] = $expire;

        if (config('app.env') != 'local') {
            $response['callback'] = $base64_callback_body;
        }

        return $response;
    }

    private function gmt_iso8601($time)
    {
        $dtStr = date('c', $time);
        $mydatetime = new \DateTime($dtStr);
        $expiration = $mydatetime->format(\DateTime::ISO8601);
        $pos = strpos($expiration, '+');
        $expiration = substr($expiration, 0, $pos);

        return $expiration.'Z';
    }
}