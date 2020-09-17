<?php

namespace TELstatic\Rakan\Gateways;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use League\Flysystem\Config;
use Qiniu\Auth;
use Qiniu\Config as QiniuConfig;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\FormUploader;
use Qiniu\Storage\ResumeUploader;
use Qiniu\Storage\UploadManager;
use TELstatic\Rakan\Interfaces\GatewayApplicationInterface;

class Qiniu implements GatewayApplicationInterface
{
    protected $accessKey;
    protected $secretKey;
    protected $bucket;
    protected $expire;
    protected $host;

    protected $auth;
    protected $bucketManger;
    protected $uploadManager;

    public function __construct()
    {
        $this->accessKey = config('rakan.gateways.qiniu.access_key');
        $this->secretKey = config('rakan.gateways.qiniu.secret_key');
        $this->bucket = config('rakan.gateways.qiniu.bucket');
        $this->expire = config('rakan.gateways.qiniu.expire');
        $this->host = config('rakan.gateways.qiniu.host');

        $this->auth = new Auth($this->accessKey, $this->secretKey);
        $this->bucketManger = new BucketManager($this->auth);
        $this->uploadManager = new UploadManager();
    }

    public function config($config)
    {
        $this->accessKey = $config['access_key'] ?? config('rakan.gateways.qiniu.access_key');
        $this->secretKey = $config['secret_key'] ?? config('rakan.gateways.qiniu.secret_key');
        $this->bucket = $config['bucket'] ?? config('rakan.gateways.qiniu.bucket');
        $this->expire = $config['expire'] ?? config('rakan.gateways.qiniu.expire');
        $this->host = $config['host'] ?? config('rakan.gateways.qiniu.host');

        $this->auth = new Auth($this->accessKey, $this->secretKey);
        $this->bucketManger = new BucketManager($this->auth);
        $this->uploadManager = new UploadManager();

        return $this;
    }

    public function signature($file)
    {
        return $this->auth->privateDownloadUrl(rtrim($this->host, '/').'/'.$file, $this->expire);
    }

    public function multiUpload($path, $file, $options)
    {
        $token = $this->auth->uploadToken($this->bucket, $path);

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        $mime = finfo_file($finfo, $file);
        finfo_close($finfo);

        try {
            $resumeUploader = new ResumeUploader(
                $token,
                $path,
                fopen($file, 'r'),
                filesize($file),
                $params = [],
                $mime,
                new \Qiniu\Config()
            );

            $result = $resumeUploader->upload($file);

            return $this->getUrl($result[0]['key']);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function policy($route = 'rakan.callback')
    {
        if (config('app.env') != 'local') {
            $policy = [
                'callbackUrl'  => route($route, ['gateway' => 'qiniu', 'bucket' => $this->bucket]),
                'callbackBody' => 'filename=$(key)&size=$(fsize)&mimeType=$(mimeType)&width=$(imageInfo.width)&height=$(imageInfo.height)',
            ];
        } else {
            $policy = [];
        }

        $token = $this->auth->uploadToken($this->bucket, null, $this->expire, $policy);

        $response = [];

        $response['data']['token'] = $token;
        $response['data']['key'] = '';
        $response['expire'] = $this->expire;

        return $response;
    }

    public function verify()
    {
        $auth = new Auth($this->accessKey, $this->secretKey);

        $callbackBody = file_get_contents('php://input');
        $contentType = 'application/x-www-form-urlencoded';
        $authorization = $_SERVER['HTTP_AUTHORIZATION'];
        $url = route('rakan.callback', ['gateway' => 'qiniu']);

        $ok = $auth->verifyCallback($contentType, $authorization, $url, $callbackBody);

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

    public function write($path, $contents, Config $config)
    {
        $token = $this->auth->uploadToken($this->bucket, $path);

        list($ret, $error) = $this->uploadManager->put($token, $path, $contents);

        if ($error !== null) {
            return false;
        } else {
            return $this->getUrl($ret['key']);
        }
    }

    public function writeStream($path, $resource, Config $config)
    {
        $token = $this->auth->uploadToken($this->bucket, $path);

        list($ret, $error) = $this->putFile($token, $path, $resource);

        if ($error !== null) {
            return false;
        } else {
            return $this->getUrl($ret['key']);
        }
    }

    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    public function updateStream($path, $resource, Config $config)
    {
        return $this->writeStream($path, $resource, $config);
    }

    private function putFile(
        $upToken,
        $key,
        $resource,
        $params = null,
        $mime = 'application/octet-stream',
        $checkCrc = false
    ) {
        if ($resource === false) {
            throw new \Exception('file can not open', 1);
        }

        $params = UploadManager::trimParams($params);
        $stat = fstat($resource);
        $size = $stat['size'];

        if ($size <= QiniuConfig::BLOCK_SIZE) {
            $data = fread($resource, $size);
            fclose($resource);

            if ($data === false) {
                throw new \Exception('file can not read', 1);
            }

            $result = FormUploader::put($upToken, $key, $data, new QiniuConfig(), $params, $mime, basename($key));

            return $result;
        }

        $up = new ResumeUploader($upToken, $key, $resource, $size, $params, $mime, new QiniuConfig());

        $ret = $up->upload(basename($key));

        fclose($resource);

        return $ret;
    }

    public function setVisibility($path, $visibility)
    {
        throw new \Exception('qiniu does not support object acl');
    }

    public function copy($path, $newpath)
    {
        list($ret, $error) = $this->bucketManger->copy($this->bucket, $path, $this->bucket, $newpath);
        if ($error !== null) {
            Log::error($error);

            return false;
        }

        return true;
    }

    public function createDir($dirname, Config $config)
    {
        return true;
    }

    public function rename($path, $newpath)
    {
        list($ret, $error) = $this->bucketManger->move($this->bucket, $path, $this->bucket, $newpath);

        if ($error !== null) {
            Log::error($error);

            return false;
        }

        return true;
    }

    public function delete($path)
    {
        if (is_string($path)) {
            $error = $this->bucketManger->delete($this->bucket, $path);

            if ($error !== null) {
                Log::error($error);

                return false;
            }

            return true;
        } elseif (is_array($path)) {
            $operations = $this->bucketManger::buildBatchDelete($this->bucket, $path);

            //todo 测试
            $res = $this->bucketManger->batch($operations);

            return true;
        } else {
            return false;
        }
    }

    public function deleteDir($dirname)
    {
        $files = $this->listContents($dirname);

        foreach ($files as $file) {
            $this->delete($file['path']);
        }

        return true;
    }

    public function read($path)
    {
        return ['contents' => file_get_contents('http:'.$this->getUrl($path))];
    }

    public function readStream($path)
    {
        return $this->read($path);
    }

    protected function getMetadata($path)
    {
        list($ret, $error) = $this->bucketManger->stat($this->bucket, $path);
        if ($error !== null) {
            return false;
        } else {
            return $ret;
        }
    }

    public function has($path)
    {
        $meta = $this->getMetadata($path);

        if ($meta) {
            return true;
        }

        return false;
    }

    public function listContents($directory = '', $recursive = false)
    {
        list($items, $error) = $this->bucketManger->listFiles($this->bucket, $directory);

        if ($error !== null) {
            Log::error($error->message());

            return [];
        } else {
            $contents = [];
            foreach (current($items) as $item) {
                $normalized = [
                    'type'      => 'file',
                    'path'      => $item['key'],
                    'timestamp' => $item['putTime'],
                ];
                if ($normalized['type'] === 'file') {
                    $normalized['size'] = $item['fsize'];
                }
                array_push($contents, $normalized);
            }

            return $contents;
        }
    }

    public function getSize($path)
    {
        $stat = $this->getMetadata($path);

        if ($stat) {
            return ['size' => $stat['fsize']];
        }

        return false;
    }

    public function getMimetype($path)
    {
        $stat = $this->getMetadata($path);

        if ($stat) {
            return ['mimetype' => $stat['mimeType']];
        }

        return false;
    }

    public function getTimestamp($path)
    {
        $stat = $this->getMetadata($path);

        if ($stat) {
            return ['timestamp' => Carbon::createFromTimestampMs($stat['putTime'] / 10000)->timestamp];
        }

        return false;
    }

    public function getUrl($path)
    {
        return rtrim($this->host, '/').'/'.$path;
    }

    public function getVisibility($path)
    {
        throw new \Exception('qiniu does not support object acl');
    }
}
