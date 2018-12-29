<?php

namespace TELstatic\Rakan\Gateways;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use League\Flysystem\Config;
use TELstatic\Rakan\Interfaces\GatewayApplicationInterface;
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\FormUploader;
use Qiniu\Storage\ResumeUploader;
use Qiniu\Config as QiniuConfig;

class Qiniu implements GatewayApplicationInterface
{
    protected $accessKey;
    protected $secretKey;
    protected $bucket;
    protected $expire;
    protected $endpoint;
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
        $this->endpoint = config('rakan.gateways.qiniu.endpoint');
        $this->host = config('rakan.gateways.qiniu.host');

        $this->auth = new Auth($this->accessKey, $this->secretKey);
        $this->bucketManger = new BucketManager($this->auth);
        $this->uploadManager = new UploadManager();
    }

    public function policy()
    {
        if (config('app.env') != 'local') {
            $policy = [
                'callbackUrl'  => route('rakan.callback', ['gateway' => 'qiniu']),
                'callbackBody' => '{"filename":"$(key)", "size":"$(fsize)","mimeType":"$(mimeType),"width":"$(imageInfo.width)","height":"$(imageInfo.height)"'
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
            header("http/1.1 403 Forbidden");
            exit();
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

    public function write($path, $contents, Config $config)
    {
        $token = $this->auth->uploadToken($this->bucket, $path);

        list($ret, $error) = $this->uploadManager->put($token, $path, $contents);

        if ($error !== null) {
            return false;
        } else {
            return $ret;
        }
    }

    public function writeStream($path, $resource, Config $config)
    {
        $token = $this->auth->uploadToken($this->bucket, $path);

        list($ret, $error) = $this->putFile($token, $path, $resource);

        if ($error !== null) {
            return false;
        } else {
            return $ret;
        }
    }

    private function putFile($upToken, $key, $resource, $params = null, $mime = 'application/octet-stream', $checkCrc = false)
    {
        if ($resource === false) {
            throw new \Exception("file can not open", 1);
        }

        $params = UploadManager::trimParams($params);
        $stat = fstat($resource);
        $size = $stat['size'];

        if ($size <= QiniuConfig::BLOCK_SIZE) {
            $data = fread($resource, $size);
            fclose($resource);

            if ($data === false) {
                throw new \Exception("file can not read", 1);
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
        $error = $this->bucketManger->delete($this->bucket, $path);

        if ($error !== null) {
            Log::error($error);
            return false;
        }

        return true;
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

    public function listContents($dirname)
    {

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