<?php

namespace TELstatic\Rakan\Gateways;

use Illuminate\Support\Facades\Log;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Util;
use OSS\Core\OssException;
use OSS\OssClient;
use TELstatic\Rakan\Interfaces\GatewayApplicationInterface;

class Oss implements GatewayApplicationInterface
{
    protected $accessKey;
    protected $secretKey;
    protected $host;
    protected $expire;
    protected $endpoint;
    protected $bucket;

    protected $client;

    protected $options = [];

    public function config($config)
    {
        $this->accessKey = $config['access_key'] ?? config('rakan.gateways.oss.access_key');
        $this->secretKey = $config['secret_key'] ?? config('rakan.gateways.oss.secret_key');
        $this->host = $config['host'] ?? config('rakan.gateways.oss.host');
        $this->expire = $config['expire'] ?? config('rakan.gateways.oss.expire');
        $this->endpoint = $config['endpoint'] ?? config('rakan.gateways.oss.endpoint');
        $this->bucket = $config['bucket'] ?? config('rakan.gateways.oss.bucket');

        $this->client = new OssClient($this->accessKey, $this->secretKey, $this->endpoint);

        return $this;
    }

    public function signature($file)
    {
        $expire = time() + $this->expire;

        $stringToSign = "GET\n\n\n".$expire."\n/".$this->bucket.'/'.$file;

        $sign = base64_encode(hash_hmac('sha1', $stringToSign, $this->secretKey, true));

        $url = $this->host.$file.'?OSSAccessKeyId='.$this->accessKey.'&Expires='.$expire.'&Signature='.urlencode($sign);

        return $url;
    }

    public function multiUpload(
        $path,
        $file,
        $options = [
            OssClient::OSS_CHECK_MD5 => true,
            OssClient::OSS_PART_SIZE => 1024 * 100,
        ]
    ) {
        try {
            $result = $this->client->multiuploadFile($this->bucket, ltrim($path, '/'), $file, $options);

            return $result['oss-request-url'];
        } catch (OssException $e) {
            Log::error($e->getMessage());

            return false;
        }
    }

    public function policy($route = 'rakan.callback')
    {
        $callback_param = [
            'callbackUrl'      => route($route, ['gateway' => 'oss', 'bucket' => $this->bucket]),
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
            '/',
        ];

        //todo
//        $conditions[] = $start;

        $arr = [
            'expiration' => $expiration,
            'conditions' => $conditions,
        ];

        $policy = json_encode($arr);
        $base64_policy = base64_encode($policy);
        $string_to_sign = $base64_policy;
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $this->secretKey, true));

        $response = [];

        $response['data']['OSSAccessKeyId'] = $this->accessKey;
        $response['data']['Policy'] = $base64_policy;
        $response['data']['Signature'] = $signature;
        $response['data']['success_action_status'] = 200;
        $response['data']['key'] = '';

        $response['expire'] = $expire;
        $response['expire_at'] = date('Y-m-d H:i:s', time() + $expire);

        if (config('app.env') != 'local') {
            $response['data']['callback'] = $base64_callback_body;
        }

        return $response;
    }

    public function verify()
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
            header('http/1.1 403 Forbidden');
            exit();
        }

        $authorization = base64_decode($authorizationBase64);

        $pubKeyUrl = base64_decode($pubKeyUrlBase64);

        $pubKey = file_get_contents($pubKeyUrl);

        if ($pubKey == '') {
            header('http/1.1 403 Forbidden');
            exit();
        }

        $path = $auth['path'];

        $pos = strpos($path, '?');
        if ($pos === false) {
            $authStr = urldecode($path)."\n".$auth['body']; // '\n' 导致验证失败
        } else {
            $authStr = urldecode(substr($path, 0, $pos)).substr($path, $pos, strlen($path) - $pos)."\n".$auth['body'];
        }

        $ok = openssl_verify($authStr, $authorization, $pubKey, OPENSSL_ALGO_MD5);

        if (!$ok) {
            header('http/1.1 403 Forbidden');
            exit();
        }
    }

    public function base64($path, $data)
    {
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $data, $result)) {
            $contents = base64_decode(substr($data, strpos($data, ',') + 1));

            $config = new Config();

            return $this->write($path, $contents, $config);
        }

        throw new \Exception('Invalid base64 str');
    }

    public function symlink($symlink, $file)
    {
        $symlink = $this->client->putSymlink($this->bucket, $symlink, $file);

        if ($symlink) {
            return true;
        }

        return false;
    }

    public function write($path, $contents, Config $config)
    {
        try {
            $res = $this->client->putObject($this->bucket, $path, $contents);
        } catch (OssException $e) {
            Log::error($e->getMessage());

            return false;
        }

        return trim($res['oss-request-url'], 'http:');
    }

    public function writeStream($path, $resource, Config $config)
    {
        $contents = stream_get_contents($resource);

        return $this->write($path, $contents, $config);
    }

    public function writeFile($path, $filePath, Config $config)
    {
        try {
            $this->client->uploadFile($this->bucket, $path, $filePath, false);
        } catch (OssException $e) {
            Log::error($e->getMessage());

            return false;
        }

        return true;
    }

    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    public function updateStream($path, $resource, Config $config)
    {
        $contents = stream_get_contents($resource);

        return $this->writeStream($path, $contents, $config);
    }

    public function rename($path, $newPath)
    {
        if (!$this->copy($path, $newPath)) {
            return false;
        }

        return $this->delete($path);
    }

    public function copy($path, $newPath)
    {
        try {
            $this->client->copyObject($this->bucket, $path, $this->bucket, $newPath);

            return true;
        } catch (OssException $e) {
            Log::error($e->getMessage());

            return false;
        }
    }

    public function delete($path)
    {
        if (is_string($path)) {
            try {
                $this->client->deleteObject($this->bucket, $path);

                return true;
            } catch (OssException $e) {
                Log::error($e->getMessage());

                return false;
            }
        } elseif (is_array($path)) {
            try {
                $this->client->deleteObjects($this->bucket, $path);

                return true;
            } catch (OssException $e) {
                Log::error($e->getMessage());

                return false;
            }
        } else {
            return false;
        }
    }

    public function has($path)
    {
        return $this->client->doesObjectExist($this->bucket, $path);
    }

    public function deleteDir($dirname)
    {
        $dirname = rtrim($dirname, '/').'/';
        $dirObjects = $this->listDirObjects($dirname, true);
        if (count($dirObjects['objects']) > 0) {
            foreach ($dirObjects['objects'] as $object) {
                $objects[] = $object['Key'];
            }

            try {
                $this->client->deleteObjects($this->bucket, $objects);
            } catch (OssException $e) {
                Log::error($e->getMessage());

                return false;
            }
        }

        try {
            $this->client->deleteObject($this->bucket, $dirname);
        } catch (OssException $e) {
            Log::error($e->getMessage());

            return false;
        }

        return true;
    }

    public function getUrl($path)
    {
        return rtrim($this->host, '/').'/'.$path;
    }

    public function createDir($dirname, Config $config)
    {
        try {
            $this->client->createObjectDir($this->bucket, $dirname, false);
        } catch (OssException $e) {
            Log::error($e->getMessage());

            return false;
        }

        return true;
    }

    public function read($path)
    {
        $result = $this->readObject($path);

        $result['contents'] = (string) $result['Body'];
        unset($result['Body']);

        return $result;
    }

    public function readStream($path)
    {
        $result = $this->readObject($path);
        $result['stream'] = $result['raw_contents'];
        rewind($result['stream']);
        $result['raw_contents']->detachStream();
        unset($result['raw_contents']);

        return $result;
    }

    protected function readObject($path)
    {
        $result['Body'] = $this->client->getObject($this->bucket, $path);
        $result = array_merge($result, ['type' => 'file']);

        return $result;
    }

    public function getMetadata($path)
    {
        try {
            $objectMeta = $this->client->getObjectMeta($this->bucket, $path);
        } catch (OssException $e) {
            Log::error($e->getMessage());

            return false;
        }

        return $objectMeta;
    }

    public function getSize($path)
    {
        $object = $this->getMetadata($path);
        $object['size'] = $object['content-length'];

        return $object;
    }

    public function getMimetype($path)
    {
        if ($object = $this->getMetadata($path)) {
            $object['mimetype'] = $object['content-type'];
        }

        return $object;
    }

    public function getTimestamp($path)
    {
        if ($object = $this->getMetadata($path)) {
            $object['timestamp'] = strtotime($object['last-modified']);
        }

        return $object;
    }

    protected function getBucketAcl()
    {
        try {
            $res['visibility'] = $this->client->getBucketAcl($this->bucket);
        } catch (OssException $e) {
            Log::error($e->getMessage());

            return 'private';
        }

        return $res['visibility'];
    }

    public function getVisibility($path)
    {
        try {
            $res['visibility'] = $this->client->getObjectAcl($this->bucket, $path);
        } catch (OssException $e) {
            Log::error($e->getMessage());

            return;
        }

        if ($res['visibility'] == 'default') {
            $res['visibility'] = $this->getBucketAcl();
        }

        switch ($res['visibility']) {
            default:
            case OssClient::OSS_ACL_TYPE_PRIVATE:
                $res['visibility'] = AdapterInterface::VISIBILITY_PRIVATE;
                break;
            case  OssClient::OSS_ACL_TYPE_PUBLIC_READ:
                $res['visibility'] = AdapterInterface::VISIBILITY_PUBLIC;
                break;
            case OssClient::OSS_ACL_TYPE_PUBLIC_READ_WRITE:
                $res['visibility'] = AdapterInterface::VISIBILITY_PUBLIC;
                break;
        }

        return $res;
    }

    public function setVisibility($path, $visibility)
    {
        $acl = ($visibility === AdapterInterface::VISIBILITY_PUBLIC) ? 'public-read' : 'private';
        $res = $this->client->putBucketAcl($this->bucket, $acl);

        if ($res) {
            return true;
        }

        return false;
    }

    public function listDirObjects($dirname = '', $recursive = false)
    {
        $delimiter = '/';
        $nextMarker = '';
        $maxkeys = 1000;
        $options = [
            'delimiter' => $delimiter,
            'prefix'    => $dirname,
            'max-keys'  => $maxkeys,
            'marker'    => $nextMarker,
        ];

        try {
            $listObjectInfo = $this->client->listObjects($this->bucket, $options);
        } catch (OssException $e) {
            Log::error($e->getMessage());

            return;
        }

        $objectList = $listObjectInfo->getObjectList(); // 文件列表
        $prefixList = $listObjectInfo->getPrefixList(); // 目录列表
        if (!empty($objectList)) {
            foreach ($objectList as $objectInfo) {
                $object['Prefix'] = $dirname;
                $object['Key'] = $objectInfo->getKey();
                $object['LastModified'] = $objectInfo->getLastModified();
                $object['eTag'] = $objectInfo->getETag();
                $object['Type'] = $objectInfo->getType();
                $object['Size'] = $objectInfo->getSize();
                $object['StorageClass'] = $objectInfo->getStorageClass();
                $dir['objects'][] = $object;
            }
        } else {
            $dir['objects'] = [];
        }
        if (!empty($prefixList)) {
            foreach ($prefixList as $prefixInfo) {
                $dir['prefix'][] = $prefixInfo->getPrefix();
            }
        } else {
            $dir['prefix'] = [];
        }
        if ($recursive) {
            foreach ($dir['prefix'] as $pfix) {
                $next = [];
                $next = $this->listDirObjects($pfix, $recursive);
                $dir['objects'] = array_merge($dir['objects'], $next['objects']);
            }
        }

        return $dir;
    }

    public function listContents($dirname)
    {
        $dir = $this->listDirObjects($dirname, true);

        $contents = $dir['objects'];
        $result = array_map([$this, 'normalizeResponseOri'], $contents);

        $result = array_filter($result, function ($value) {
            return $value['path'] !== false;
        });

        return $result;
    }

    protected function removePathPrefix($path)
    {
        return substr($path, strlen(''));
    }

    protected static $resultMap = [
        'Body'           => 'raw_contents',
        'Content-Length' => 'size',
        'ContentType'    => 'mimetype',
        'Size'           => 'size',
        'StorageClass'   => 'storage_class',
    ];

    protected function normalizeResponseOri(array $object, $path = null)
    {
        $result = ['path' => $path ?: $this->removePathPrefix(isset($object['Key']) ? $object['Key'] : $object['Prefix'])];
        $result['dirname'] = Util::dirname($result['path']);
        if (isset($object['LastModified'])) {
            $result['timestamp'] = strtotime($object['LastModified']);
        }
        if (substr($result['path'], -1) === '/') {
            $result['type'] = 'dir';
            $result['path'] = rtrim($result['path'], '/');

            return $result;
        }

        $result = array_merge($result, Util::map($object, static::$resultMap), ['type' => 'file']);

        return $result;
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
