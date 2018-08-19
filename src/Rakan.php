<?php

namespace TELstatic\Rakan;

use Illuminate\Support\Facades\Log;
use TELstatic\Rakan\Models\Files;
use OSS\OssClient;
use OSS\Core\OSSException;
use Illuminate\Http\Request;

class Rakan
{
    public function __construct()
    {

    }

    /**
     * 初始化目录
     */
    public function initFolder($path, $module, $target_id)
    {
        $data = [
            'path'      => $path,
            'name'      => 'Root',
            'type'      => 'folder',
            'pid'       => 0,
            'module'    => $module,
            'target_id' => $target_id,
            'sort'      => 255,
        ];

        $bool = Files::create($data);
        return $bool;
    }

    /**
     * 获取文件列表
     */
    public static function getFiles($parentFolder)
    {
        $where = [
            'pid' => $parentFolder->_id
        ];

        $children = Files::where($where)->orderBy('sort', 'desc')->paginate(50);

        $data = [
            'parent'   => $parentFolder,
            'children' => $children
        ];

        return $data;
    }

    /**
     * 创建文件夹
     *
     */
    public function createFolder(Request $request)
    {
        $parentFolder = Files::findOrFail($request->get('pid'));

        $where = [
            'pid'  => $request->get('pid'),
            'type' => 'folder'
        ];

        $childFolders = Files::where($where)->count('_id');

        if ($childFolders >= 10) {
            return 202;
        }

        $where = [];

        $where[] = [
            'pid', $parentFolder->_id,
        ];

        $where[] = [
            'name', $request->name,
        ];

        $folder = Files::where($where)->first();

        if (!$folder) {
            $data = [
                'path'      => $parentFolder->path . '/' . $request->name,
                'name'      => $request->name,
                'type'      => 'folder',
                'pid'       => $parentFolder->_id,
                'module'    => $parentFolder->module,
                'target_id' => $parentFolder->target_id,
                'sort'      => 255
            ];

            $bool = Files::create($data);

            return $bool;
        } else {
            return 201;
        }
    }

    /**
     * 检查文件是否存在
     */
    public function checkFile(Request $request)
    {
        $object = $request->path;

        $accessKeyId = config('rakan.alioss.ak');
        $accessKeySecret = config('rakan.alioss.sk');
        $endpoint = config('rakan.alioss.endpoint');
        $bucket = config('rakan.alioss.bucket');

        try {
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
        } catch (OSSException $e) {
            Log::error($e->getMessage());
        }

        $exist = $ossClient->doesObjectExist($bucket, $object);

        return $exist;
    }

    /**
     * 创建文件
     */
    public static function saveFiles(Request $request)
    {
        self::verifyPost();

        $fileInfo = pathinfo($request->filename);

        $folder = Files::where(['path' => $fileInfo['dirname']])->firstOrFail();

        $data = [
            'path'      => $request->filename,
            'size'      => $request->size,
            'width'     => $request->width,
            'height'    => $request->height,
            'ext'       => $request->mimeType,
            'file_name' => $fileInfo['basename'],
            'module'    => $folder->module,
            'target_id' => $folder->target_id,
            'pid'       => $folder->pid,
            'sort'      => 0,
            'type'      => 'file'
        ];

        $bool = Files::create($data);

        return $bool;
    }

    private static function verifyPost()
    {
        $auth = [
            'authorizationBase64' => $_SERVER['HTTP_AUTHORIZATION'],
            'pubKeyUrlBase64'     => $_SERVER['HTTP_X_OSS_PUB_KEY_URL'],
            'path'                => $_SERVER['REQUEST_URI'],
            'body'                => file_get_contents('php://input'),
        ];

        $authorizationBase64 = $auth['authorizationBase64'];
        $pubKeyUrlBase64 = $auth['pubKeyUrlBase64'];

        if ($authorizationBase64 == '' || $pubKeyUrlBase64 == '') {
            abort(403);
        }

        $authorization = base64_decode($authorizationBase64);

        $pubKeyUrl = base64_decode($pubKeyUrlBase64);

        $pubKey = file_get_contents($pubKeyUrl);

        if ($pubKey == "") {
            abort(403);
        }

        $path = $auth['path'];

        $pos = strpos($path, '?');
        if ($pos === false) {
            $authStr = urldecode($path) . "\n" . $auth['body'];
        } else {
            $authStr = urldecode(substr($path, 0, $pos)) . substr($path, $pos, strlen($path) - $pos) . "\n" . $auth['body'];
        }

        $ok = openssl_verify($authStr, $authorization, $pubKey, OPENSSL_ALGO_MD5);

        if (!$ok) {
            abort(403);
        }
    }

    /**
     * 批量删除文件
     */
    public static function deleteFiles($objects, $request)
    {
        $accessKeyId = config('rakan.alioss.ak');
        $accessKeySecret = config('rakan.alioss.sk');
        $endpoint = config('rakan.alioss.endpoint');
        $bucket = config('rakan.alioss.bucket');

        $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
        $bool = $ossClient->deleteObject($bucket, $objects);

        Files::destroy($objects);

        return $bool;
    }

    /**
     * 获取签名
     */
    public static function getPolicy()
    {
        $id = config('rakan.alioss.ak');
        $key = config('rakan.alioss.sk');
        $host = config('rakan.alioss.host');
        $callbackUrl = config('rakan.alioss.callback');
        $expire = config('rakan.alioss.expire');

        $callback_param = [
            'callbackUrl'      => $callbackUrl,
            'callbackBody'     => 'filename=${object}&size=${size}&mimeType=${mimeType}&height=${imageInfo.height}&width=${imageInfo.width}',
            'callbackBodyType' => "application/x-www-form-urlencoded",
        ];
        $callback_string = json_encode($callback_param);
        $base64_callback_body = base64_encode($callback_string);

        $now = time();

        //设置该policy超时时间是120s. 即这个policy过了这个有效时间，将不能访问
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
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $key, true));

        $response = [];
        $response['accessid'] = $id;
        $response['host'] = $host;
        $response['policy'] = $base64_policy;
        $response['signature'] = $signature;
        $response['expire'] = $expire;
        $response['callback'] = $base64_callback_body;

        return $response;
    }


    private static function gmt_iso8601($time)
    {
        $dtStr = date("c", $time);
        $mydatetime = new \DateTime($dtStr);
        $expiration = $mydatetime->format(\DateTime::ISO8601);
        $pos = strpos($expiration, '+');
        $expiration = substr($expiration, 0, $pos);

        return $expiration . "Z";
    }
}
