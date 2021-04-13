<?php

namespace TELstatic\Rakan\Gateways;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Util;
use Obs\ObsClient;
use Obs\ObsException;
use TELstatic\Rakan\Interfaces\GatewayApplicationInterface;

class Obs implements GatewayApplicationInterface
{
    protected $accessKey;
    protected $secretKey;
    protected $host;
    protected $expire;
    protected $endpoint;
    protected $bucket;
    protected $acl;

    protected $client;

    public function config($config)
    {
        $this->accessKey = $config['access_key'] ?? config('rakan.gateways.obs.access_key');
        $this->secretKey = $config['secret_key'] ?? config('rakan.gateways.obs.secret_key');
        $this->host = $config['host'] ?? config('rakan.gateways.obs.host');
        $this->expire = $config['expire'] ?? config('rakan.gateways.obs.expire');
        $this->endpoint = $config['endpoint'] ?? config('rakan.gateways.obs.endpoint');
        $this->bucket = $config['bucket'] ?? config('rakan.gateways.obs.bucket');
        $this->acl = $config['acl'] ?? config('rakan.gateways.obs.acl');

        $this->client = new ObsClient([
            'key'      => $this->accessKey,
            'secret'   => $this->secretKey,
            'endpoint' => $this->endpoint,
        ]);

        return $this;
    }

    /**
     * 文件分片上传.
     *
     * @desc 文件分片上传
     *
     * @param $path
     * @param $file
     * @param array $options
     *
     * @return mixed
     *
     * @author liufanyue
     * Date: 2020/9/14
     */
    public function multiUpload(
        $path,
        $file,
        $options = [
            'partSize' => 1024 * 10,
        ]
    ) {
        $partSize = $options['partSize'];

        if ($partSize < 1024 * 100 || $partSize > 5 * 1024 * 1024 * 1024) {
            \Log::info('分割大小不在[100K,5G]范围之内');
        }

        try {
            $res = $this->client->initiateMultipartUpload([
                'Bucket' => $this->bucket,
                'Key'    => $path,
            ]);

            $uploadId = $res['UploadId'];
            $totalSize = filesize($file);
            $start = 0;
            $batchNumber = (int) ceil($totalSize / $partSize);
            $result = false;

            for ($i = 0; $i < $batchNumber; $i++) {
                $parts[$i] = file_get_contents($file, false, null, $start, $partSize);

                $start = $start + $partSize;
            }

            for ($j = 0; $j < $batchNumber; $j++) {
                $this->client->uploadPart([
                    'Bucket'     => $this->bucket,
                    'Key'        => $path,
                    'PartNumber' => $j + 1,
                    'Body'       => $parts[$j],
                    'UploadId'   => $uploadId,
                ]);

                if ($j === ($batchNumber - 1)) {
                    $list = $this->client->listParts([
                        'Bucket'   => $this->bucket,
                        'Key'      => $path,
                        'UploadId' => $uploadId,
                    ]);

                    if ($list['Parts']) {
                        $result = $this->client->completeMultipartUpload([
                            'Bucket'   => $this->bucket,
                            'Key'      => $path,
                            'UploadId' => $uploadId,
                            'Parts'    => $list['Parts'],
                        ]);

                        $result = $result->toArray();
                    }
                }
            }

            return $result['Location'];
        } catch (ObsException $obsException) {
            return false;
        }
    }

    /**
     * 生成带授权信息的URL.
     *
     * @desc 生成带授权信息的URL
     *
     * @param $file
     *
     * @return bool
     *
     * @author liufanyue
     * Date: 2020/9/17
     */
    public function signature($file)
    {
        try {
            $resp = $this->client->createSignedUrl([
                'Method'  => 'GET',
                'Bucket'  => $this->bucket,
                'Expires' => 3600,
                'Key'     => $file,
            ]);

            return $resp['SignedUrl'];
        } catch (ObsException $obsException) {
            return false;
        }
    }

    /**
     * 生成带授权信息的表单上传参数.
     *
     * @desc 生成带授权信息的表单上传参数
     *
     * @param string $route
     *
     * @return array|bool
     *
     * @author liufanyue
     * Date: 2020/9/17
     */
    public function policy($route = 'rakan.callback')
    {
        try {
            $res = $this->client->createPostSignature([
                'Bucket' => $this->bucket,
            ]);
        } catch (ObsException $obsException) {
            return false;
        }

        $response = [];

        $response['data']['AccessKeyId'] = $this->accessKey;
        $response['data']['Policy'] = $res['Policy'];
        $response['data']['Signature'] = $res['Signature'];
        $response['data']['key'] = '';
        $response['expire'] = $this->expire;
        $response['expire_at'] = date('Y-m-d H:i:s', time() + $this->expire);

        return $response;
    }

    public function verify()
    {
        \Log::info('OBS 暂不支持回调验证');
    }

    public function write($path, $contents, Config $config)
    {
        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $path,
                'Body'   => $contents,
            ]);

            return true;
        } catch (ObsException $obsException) {
            return false;
        }
    }

    public function writeStream($path, $resource, Config $config)
    {
        $contents = stream_get_contents($resource);

        return $this->write($path, $contents, $config);
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

    public function rename($path, $newpath)
    {
        if (!$this->copy($path, $newpath)) {
            return false;
        }

        return $this->delete($path);
    }

    public function copy($path, $newpath)
    {
        try {
            $this->client->copyObject([
                'Bucket'     => $this->bucket,
                'Key'        => $newpath,
                'CopySource' => $this->bucket.'/'.$path,
            ]);

            return true;
        } catch (ObsException $e) {
            return false;
        }
    }

    public function delete($path)
    {
        if (is_string($path)) {
            try {
                $this->client->deleteObject(
                    [
                        'Bucket' => $this->bucket,
                        'Key'    => $path,
                    ]
                );

                return true;
            } catch (ObsException $e) {
                return false;
            }
        } elseif (is_array($path)) {
            try {
                $objects = [];

                foreach ($path as $value) {
                    $objects[] = [
                        'Key' => $value,
                    ];
                }

                $this->client->deleteObjects(
                    [
                        'Bucket'  => $this->bucket,
                        'Objects' => $objects,
                    ]
                );

                return true;
            } catch (ObsException $e) {
                return false;
            }
        } else {
            return false;
        }
    }

    public function deleteDir($dirname)
    {
        return false;
    }

    public function createDir($dirname, Config $config)
    {
        return false;
    }

    public function setVisibility($path, $visibility)
    {
        return false;
    }

    public function getVisibility($path)
    {
        $res['visibility'] = $this->acl;

        switch ($res['visibility']) {
            case 0:
                $res['visibility'] = AdapterInterface::VISIBILITY_PRIVATE;
                break;
            default:
            case  1:
            case 2:
                $res['visibility'] = AdapterInterface::VISIBILITY_PUBLIC;
                break;
        }

        return $res;
    }

    protected function removePathPrefix($path)
    {
        return substr($path, strlen(''));
    }

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

    /**
     * 获取文件大小.
     *
     * @desc 获取文件大小
     *
     * @param $path
     *
     * @return mixed
     *
     * @author liufanyue
     * Date: 2020/9/17
     */
    public function getSize($path)
    {
        $result = $this->client->getObjectMetadata([
            'Bucket' => $this->bucket,
            'Key'    => $path,
        ]);

        $object['size'] = $result['ContentLength'];

        return $object;
    }

    /**
     * 获取文件最后修改时间.
     *
     * @desc 获取文件最后修改时间
     *
     * @param $path
     *
     * @return mixed
     *
     * @author liufanyue
     * Date: 2020/9/17
     */
    public function getTimestamp($path)
    {
        $result = $this->client->getObjectMetadata(
            [
                'Bucket' => $this->bucket,
                'Key'    => $path,
            ]
        );

        $object['timestamp'] = strtotime($result->toArray()['LastModified']);

        return $object;
    }

    public function has($path)
    {
        try {
            $res = $this->client->getObjectMetadata(
                [
                    'Bucket' => $this->bucket,
                    'Key'    => $path,
                ]
            );

            return true;
        } catch (ObsException $exception) {
            return false;
        }
    }

    public function read($path)
    {
        $result = $this->client->getObject([
            'Bucket' => $this->bucket,
            'Key'    => $path,
        ]);

        return [
            'type'     => 'file',
            'contents' => (string) $result->toArray()['Body'],
        ];
    }

    public function readStream($path)
    {
        return $this->read([
            'Key' => $path,
        ]);
    }

    public function listContents($dirname)
    {
        \Log::info('OBS 暂不支持此功能');

        return [];
    }

    public function getUrl($path)
    {
        return rtrim($this->host, '/').'/'.$path;
    }

    public function getPath($file)
    {
        $host = str_replace(['https:', 'http:'], '', rtrim($this->host, '/'));

        $path = str_replace(['https:', 'http:'], '', rtrim($file, '/'));

        return ltrim($path, $host);
    }
}
