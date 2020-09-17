<?php
/**
 * Created by PhpStorm.
 * User: Sakuraiyaya
 * Date: 2020/8/25
 * Time: 9:36.
 */

namespace TELstatic\Rakan\Gateways;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Util;
use Qcloud\Cos\Exception\CosException;
use Qcloud\Cos\Exception\ServiceResponseException;
use TELstatic\Rakan\Interfaces\GatewayApplicationInterface;

class Cos implements GatewayApplicationInterface
{
    protected $accessKey;
    protected $secretKey;
    protected $host;
    protected $expire;
    protected $region;
    protected $endpoint;
    protected $bucket;

    protected $client;

    const PUBLIC_GRANT_URI = 'http://cam.qcloud.com/groups/global/AllUsers';

    public function config($config)
    {
        $this->accessKey = $config['access_key'] ?? config('rakan.gateways.cos.access_key');
        $this->secretKey = $config['secret_key'] ?? config('rakan.gateways.cos.secret_key');
        $this->region = $config['region'] ?? config('rakan.gateways.cos.region');
        $this->expire = $config['expire'] ?? config('rakan.gateways.cos.expire');
        $this->bucket = $config['bucket'] ?? config('rakan.gateways.cos.bucket');
        $this->host = $config['host'] ?? config('rakan.gateways.cos.host');

        $this->client = new \Qcloud\Cos\Client([
            'region'      => $this->region,
            'schema'      => 'https',
            'credentials' => [
                'secretId'  => $this->accessKey,
                'secretKey' => $this->secretKey,
            ],
        ]);

        return $this;
    }

    /**
     * 生成请求签名.
     *
     * @desc 生成请求签名
     *
     * @param $file
     *
     * @return mixed
     *
     * @author Sakuraiyaya
     * Date: 2020/8/27
     */
    public function signature($file)
    {
        $startTimestamp = time();

        $endTimestamp = time() + $this->expire;

        $signTime = $startTimestamp.';'.$endTimestamp;

        $headerList = '';

        $urlParamList = '';

        $httpMethod = 'get';

        $signKey = hash_hmac('sha1', $signTime, $this->secretKey);

        $httpString = "$httpMethod\n$file\n";

        $sha1edHttpString = sha1($httpString);

        $stringToSign = "sha1\n$signTime\n$sha1edHttpString\n";

        $signature = hash_hmac('sha1', $stringToSign, $signKey);

        $authorization = "q-sign-algorithm=sha1&q-ak=$this->accessKey&q-sign-time=$signTime&q-key-time=$signTime&q-header-list=$headerList&q-url-param-list=$urlParamList&q-signature=$signature";

        return $authorization;
    }

    public function policy($route = 'rakan.callback')
    {
        $now = time();
        $expire = $this->expire;
        $end = $now + $expire;

        $keyTime = $now.';'.$end;

        $expiration = self::gmt_iso8601($end);

        $condition = [
            'content-length-range',
            0,
            1048576000,
        ];

        $conditions[] = $condition;

        $conditions[] = [
            'eq',
            '$q-sign-algorithm',
            'sha1',
        ];

        $conditions[] = [
            'eq',
            '$q-ak',
            $this->accessKey,
        ];

        $conditions[] = [
            'eq',
            '$q-sign-time',
            $keyTime,
        ];

        $arr = [
            'expiration' => $expiration,
            'conditions' => $conditions,
        ];

        $signKey = hash_hmac('sha1', $keyTime, $this->secretKey);

        $policy = json_encode($arr);

        $string_to_sign = sha1($policy);

        $signature = hash_hmac('sha1', $string_to_sign, $signKey);

        $response = [];

        $response['data']['q-ak'] = $this->accessKey;
        $response['data']['q-sign-algorithm'] = 'sha1';
        $response['data']['policy'] = base64_encode($policy);
        $response['data']['q-key-time'] = $keyTime;
        $response['data']['q-signature'] = $signature;
        $response['data']['key'] = '';

        $response['expire'] = $expire;
        $response['expire_at'] = date('Y-m-d H:i:s', time() + $expire);

        return $response;
    }

    public function verify()
    {
        try {
            $data = [
                'key'      => $_POST['key'],
                'filename' => $_POST['key'],
                'size'     => $_POST['size'],
                'mimeType' => $_POST['mimeType'],
                'width'    => $_POST['width'],
                'height'   => $_POST['height'],
            ];

            ksort($data);

            $sign = md5(http_build_query($data).'&secretkey='.$this->secretKey);

            if ($sign !== $_POST['sign']) {
                header('http/1.1 403 Forbidden');
                exit();
            }

            return true;
        } catch (\Exception $exception) {
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

        throw new CosException('Invalid base64 str');
    }

    /**
     * 初始化分块上传.
     *
     * @desc 初始化分块上传
     *
     * @param $path
     *
     * @return mixed
     *
     * @author Sakuraiyaya
     * Date: 2020/9/10
     */
    public function initiateMultipartUpload($path)
    {
        $result = $this->client->createMultipartUpload([
            'Bucket' => $this->bucket,
            'Key'    => $path,
        ]);

        return $result['UploadId'];
    }

    /**
     * 上传分块.
     *
     * @desc 上传分块
     *
     * @param $path
     * @param $file
     * @param $options
     *
     * @return bool
     *
     * @author Sakuraiyaya
     * Date: 2020/9/10
     */
    public function multiUpload(
        $path,
        $file,
        $options = [
            'partSize' => 1024 * 1000,
        ]
    ) {
        $min = 1024 * 1000;
        $max = 1024 * 1000 * 5000;

        if ($options['partSize'] < $min || $options['partSize'] > $max) {
            throw new CosException('上传分片大小应在1MB到5GB之间');
        }

        try {
            $uploadId = $this->initiateMultipartUpload($path);

            // 文件总大小
            $totalSize = filesize($file);

            // 设置分块大小
            $partSize = $options['partSize'];

            // 计算分块数
            $batchNumber = (int) ceil($totalSize / $partSize);

            $splits = $this->splitFile($file, $partSize, $batchNumber);

            $result = false;

            for ($i = 0; $i < $batchNumber; $i++) {
                $this->client->uploadPart([
                    'Bucket'     => $this->bucket,
                    'Key'        => $path,
                    'Body'       => $splits[$i],
                    'UploadId'   => $uploadId,
                    'PartNumber' => $i + 1,
                ]);

                if ($i === ($batchNumber - 1)) {
                    $list = $this->client->listParts([
                        'Bucket'   => $this->bucket,
                        'Key'      => $path,
                        'UploadId' => $uploadId,
                    ]);

                    if ($list['Parts']) {
                        $result = $this->completeMultipartUpload($path, $list['Parts'], $uploadId);

                        $result = $result->toArray();
                    }
                }
            }

            return $result['Location'];
        } catch (CosException $e) {
            \Log::error($e->getMessage());

            return false;
        }
    }

    /**
     * 文件分块.
     *
     * @desc 文件分块
     *
     * @param $file
     * @param $blockSize
     * @param $count
     *
     * @return array
     *
     * @author Sakuraiyaya
     * Date: 2020/9/10
     */
    protected function splitFile($file, $blockSize, $count)
    {
        $parts = [];

        $start = 0;

        for ($i = 0; $i < $count; $i++) {
            $parts[$i] = file_get_contents($file, false, null, $start, $blockSize);

            $start = $start + $blockSize;
        }

        return $parts;
    }

    /**
     * 完成分块上传.
     *
     * @desc 完成分块上传
     *
     * @param $path
     * @param $options
     * @param $uploadId
     *
     * @return mixed
     *
     * @author Sakuraiyaya
     * Date: 2020/9/10
     */
    public function completeMultipartUpload($path, $options, $uploadId)
    {
        $result = $this->client->completeMultipartUpload([
            'Bucket'   => $this->bucket,
            'Key'      => $path,
            'UploadId' => $uploadId,
            'Parts'    => $options,
        ]);

        return $result;
    }

    /**
     * 上传新文件.
     *
     * @desc 上传新文件
     *
     * @param $path
     * @param $contents
     * @param Config $config
     *
     * @return bool
     *
     * @author Sakuraiyaya
     * Date: 2020/8/26
     */
    public function write($path, $contents, Config $config)
    {
        try {
            $result = $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $path,
                'Body'   => $contents,
            ]);

            return $result['Location'];
        } catch (CosException $e) {
            \Log::error($e->getMessage());

            return false;
        }
    }

    /**
     * 使用流上传新文件.
     *
     * @desc 使用流上传新文件
     *
     * @param $path
     * @param $resource
     * @param Config $config
     *
     * @return bool
     *
     * @author Sakuraiyaya
     * Date: 2020/8/26
     */
    public function writeStream($path, $resource, Config $config)
    {
        $contents = stream_get_contents($resource);

        return $this->write($path, $contents, $config);
    }

    /**
     * 更新文件.
     *
     * @desc 更新文件
     *
     * @param $path
     * @param $contents
     * @param Config $config
     *
     * @return bool
     *
     * @author Sakuraiyaya
     * Date: 2020/8/26
     */
    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    /**
     * 使用流更新文件.
     *
     * @desc 使用流更新文件
     *
     * @param $path
     * @param $resource
     * @param Config $config
     *
     * @return bool
     *
     * @author Sakuraiyaya
     * Date: 2020/8/26
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->writeStream($path, $resource, $config);
    }

    /**
     * 重命名文件.
     *
     * @desc 重命名文件
     *
     * @param $path
     * @param $newpath
     *
     * @return bool
     *
     * @author Sakuraiyaya
     * Date: 2020/8/26
     */
    public function rename($path, $newpath)
    {
        if (!$this->copy($path, $newpath)) {
            return false;
        }

        return $this->delete($path);
    }

    /**
     * 复制文件.
     *
     * @desc 复制文件
     *
     * @param $path
     * @param $newpath
     *
     * @return bool
     *
     * @author Sakuraiyaya
     * Date: 2020/8/26
     */
    public function copy($path, $newpath)
    {
        try {
            $result = $this->client->copyObject([
                'Bucket'            => $this->bucket,
                'Key'               => $newpath,
                'CopySource'        => $this->getSourcePath($path),
                'MetadataDirective' => 'Copy',
            ]);

            return true;
        } catch (CosException $e) {
            \Log::error($e->getMessage());

            return false;
        }
    }

    /**
     * 获取资源路径.
     *
     * @desc 获取资源路径
     *
     * @param $path
     *
     * @return string
     *
     * @author Sakuraiyaya
     * Date: 2020/9/7
     */
    public function getSourcePath($path)
    {
        return sprintf('%s.cos.%s.myqcloud.com/%s', $this->bucket, $this->region, $path);
    }

    /**
     * 删除文件.
     *
     * @desc 删除文件
     *
     * @param $path
     *
     * @return bool
     *
     * @author Sakuraiyaya
     * Date: 2020/8/26
     */
    public function delete($path)
    {
        if (is_string($path)) {
            try {
                $this->client->deleteObject([
                    'Bucket' => $this->bucket,
                    'Key'    => $path,
                ]);

                return true;
            } catch (CosException $e) {
                \Log::error($e->getMessage());

                return false;
            }
        } elseif (is_array($path)) {
            $data = [];

            foreach ($path as $key => $value) {
                $data[$key]['Key'] = $value;
            }

            try {
                $result = $this->client->deleteObjects([
                    'Bucket'  => $this->bucket,
                    'Objects' => $data,
                ]);

                return $result;
            } catch (CosException $e) {
                \Log::error($e->getMessage());

                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 删除目录.
     *
     * @desc 删除目录
     *
     * @param $dirname
     *
     * @return bool
     *
     * @author Sakuraiyaya
     * Date: 2020/8/26
     */
    public function deleteDir($dirname)
    {
        // todo 文件夹中无文件时,文件夹也会被删除
        $dirObjects = $this->listDirObjects($dirname, true);

        if (!isset($dirObjects['Contents'])) {
            return true;
        }

        $keys = array_map(function ($item) {
            return ['Key' => $item['Key']];
        }, $dirObjects['Contents']);

        try {
            $this->client->deleteObjects([
                'Bucket'  => $this->bucket,
                'Objects' => $keys,
            ]);
        } catch (CosException $e) {
            \Log::error($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * 创建目录.
     *
     * @desc 创建目录
     *
     * @param $dirname
     * @param Config $config
     *
     * @return bool
     *
     * @author Sakuraiyaya
     * Date: 2020/8/26
     */
    public function createDir($dirname, Config $config)
    {
        $dirname = rtrim($dirname, '/').'/';

        return $this->write($dirname, '', $config);
    }

    /**
     * 设置文件访问权限.
     *
     * @desc 设置文件访问权限
     *
     * @param $path
     * @param $visibility
     *
     * @return bool
     *
     * @author Sakuraiyaya
     * Date: 2020/8/26
     */
    public function setVisibility($path, $visibility)
    {
        try {
            $result = $this->client->putObjectAcl([
                'Bucket' => $this->bucket,
                'Key'    => $path,
                'ACL'    => $visibility === AdapterInterface::VISIBILITY_PUBLIC ? 'public-read' : 'private',
            ]);

            return $result;
        } catch (CosException $e) {
            \Log::error($e->getMessage());

            return false;
        }
    }

    /**
     * 获取文件访问权限.
     *
     * @desc 获取文件访问权限
     *
     * @param $path
     *
     * @return array
     *
     * @author Sakuraiyaya
     * Date: 2020/8/26
     */
    public function getVisibility($path)
    {
        $response = $this->client->getObjectAcl([
            'Bucket' => $this->bucket,
            'Key'    => $path,
        ]);

        $visibility = AdapterInterface::VISIBILITY_PRIVATE;

        foreach ($response['Grants'] as $grant) {
            foreach ($grant['Grant'] as $grantee) {
                if (
                    isset($grantee['Grantee']['URI'])
                    && $grantee['Grantee']['URI'] == self::PUBLIC_GRANT_URI
                    && $grantee['Permission'] == 'READ'
                ) {
                    $visibility = AdapterInterface::VISIBILITY_PUBLIC;
                    break;
                }
            }
        }

        return compact('visibility');
    }

    /**
     * 检查文件是否存在.
     *
     * @desc 检查文件是否存在
     *
     * @param $path
     *
     * @return bool
     *
     * @author Sakuraiyaya
     * Date: 2020/8/26
     */
    public function has($path)
    {
        try {
            $result = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key'    => $path,
            ]);

            return $result;
        } catch (ServiceResponseException $e) {
            return false;
        }
    }

    /**
     * 读取文件.
     *
     * @desc 读取文件
     *
     * @param $path
     *
     * @return array|bool
     *
     * @author Sakuraiyaya
     * Date: 2020/8/26
     */
    public function read($path)
    {
        $result = $this->readObject($path);

        $result['contents'] = (string) $result['Body'];
        unset($result['Body']);

        return $result;
    }

    /**
     * 读取文件流.
     *
     * @desc 读取文件流
     *
     * @param $path
     *
     * @return mixed
     *
     * @author Sakuraiyaya
     * Date: 2020/8/26
     */
    public function readStream($path)
    {
        $response = $this->readObject($path);

        $response['stream'] = $response['contents']->getStream();

        return $response;
    }

    /**
     * 读取 Cos 对象
     *
     * @desc 读取 Cos 对象
     *
     * @param $path
     *
     * @return array|bool
     *
     * @author Sakuraiyaya
     * Date: 2020/8/26
     */
    protected function readObject($path)
    {
        try {
            $response = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key'    => $path,
            ])->toArray();

            $response = array_merge($response, ['type' => 'file']);

            return $response;
        } catch (CosException $e) {
            \Log::error($e->getMessage());

            return false;
        }
    }

    /**
     * 读取文件列表.
     *
     * @desc 读取文件列表
     *
     * @param $dirname
     *
     * @return mixed
     *
     * @author Sakuraiyaya
     * Date: 2020/8/26
     */
    public function listContents($dirname)
    {
        $result = [];

        $dir = $this->listDirObjects($dirname, true);

        foreach ($dir['Contents'] as $content) {
            $result[] = $this->normalizeResponseOri($content);
        }

        return $result;
    }

    public function listDirObjects($directory = '', $recursive = false)
    {
        $marker = '';
        $maxkeys = 1000;
        $options = [
            'Bucket'  => $this->bucket,
            'Marker'  => $marker,
            'MaxKeys' => $maxkeys,
        ];

        if ($directory) {
            $options['Prefix'] = $directory;
        }

        try {
            $objectInfo = $this->client->listObjects($options);

            $objectInfo = $objectInfo->toArray();

            return $objectInfo;
        } catch (CosException $e) {
            \Log::error($e->getMessage());

            return false;
        }
    }

    /**
     * 获取文件访问地址
     *
     * @desc 获取文件访问地址
     *
     * @param $path
     *
     * @return string
     *
     * @author Sakuraiyaya
     * Date: 2020/9/7
     */
    public function getUrl($path)
    {
        return rtrim($this->host, '/').'/'.$path;
    }

    /**
     * 获取元数据.
     *
     * @desc 获取元数据
     *
     * @param $path
     *
     * @return bool
     *
     * @author Sakuraiyaya
     * Date: 2020/9/7
     */
    public function getMetadata($path)
    {
        try {
            $objectMeta = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key'    => $path,
            ]);

            return $objectMeta->toArray();
        } catch (CosException $e) {
            \Log::error($e->getMessage());

            return false;
        }
    }

    /**
     * 获取文件大小.
     *
     * @desc 获取文件大小
     *
     * @param $path
     *
     * @return bool
     *
     * @author Sakuraiyaya
     * Date: 2020/8/26
     */
    public function getSize($path)
    {
        $object = $this->getMetadata($path);
        $object['size'] = $object['ContentLength'];

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
     * @author Sakuraiyaya
     * Date: 2020/8/26
     */
    public function getTimestamp($path)
    {
        if ($object = $this->getMetadata($path)) {
            $object['timestamp'] = strtotime($object['LastModified']);
        }

        return $object;
    }

    /**
     * 获取类型.
     *
     * @desc 获取类型
     *
     * @param $path
     *
     * @return bool
     *
     * @author Sakuraiyaya
     * Date: 2020/9/7
     */
    public function getMimetype($path)
    {
        if ($object = $this->getMetadata($path)) {
            $object['mimetype'] = $object['ContentType'];
        }

        return $object;
    }

    protected function removePathPrefix($path)
    {
        return substr($path, strlen(''));
    }

    protected static $resultMap = [
        'Body'          => 'contents',
        'ContentLength' => 'size',
        'ContentType'   => 'mimetype',
        'Size'          => 'size',
        'StorageClass'  => 'storageclass',
    ];

    protected function normalizeResponseOri(array $object, $path = null)
    {
        $result = [
            'path' => $path ?: $this->removePathPrefix(isset($object['Key']) ? $object['Key'] : $object['Prefix']),
        ];

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
