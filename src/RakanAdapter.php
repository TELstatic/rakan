<?php

namespace TELstatic\Rakan;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Util;
use OSS\Core\OssException;
use OSS\OssClient;
use Illuminate\Support\Facades\Log;

class RakanAdapter extends AbstractAdapter
{
    public $client;

    public $bucket;


    public function __construct()
    {
        $accessKeyId = config('rakan.oss.access_id');
        $accessKeySecret = config('rakan.oss.access_key');
        $endpoint = config('rakan.oss.endpoint');
        $this->bucket = config('rakan.oss.bucket');

        try {
            $this->client = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
        } catch (OssException $exception) {
            Log::error(__FUNCTION__, $exception);
        }
    }

    public function write($path, $contents, Config $config)
    {
    }

    public function writeStream($path, $resource, Config $config)
    {
        $object = $this->applyPathPrefix($path);
        $options = $this->getOptions($this->options, $config);
        $options[OssClient::OSS_CHECK_MD5] = true;
        if (!isset($options[OssClient::OSS_CONTENT_TYPE])) {
            $options[OssClient::OSS_CONTENT_TYPE] = Util::guessMimeType($path, '');
        }
        try {
            $this->client->uploadFile($this->bucket, $object, $filePath, $options);
        } catch (OssException $e) {
            Log::error(__FUNCTION__, $e);
            return false;
        }
        return $this->normalizeResponse($options, $path);
    }

    public function update($path, $contents, Config $config)
    {
        // TODO: Implement update() method.
    }

    public function updateStream($path, $resource, Config $config)
    {
        // TODO: Implement updateStream() method.
    }

    public function read($path)
    {
        // TODO: Implement read() method.
    }

    public function readStream($path)
    {
        // TODO: Implement readStream() method.
    }

    public function copy($path, $newpath)
    {
        $object = $this->applyPathPrefix($path);
        $newObject = $this->applyPathPrefix($newpath);
        try {
            $this->client->copyObject($this->bucket, $object, $this->bucket, $newObject);
        } catch (OssException $e) {
            Log::error(__FUNCTION__, $e);
            return false;
        }
        return true;
    }

    public function rename($path, $newpath)
    {
        if (! $this->copy($path, $newpath)) {
            return false;
        }

        return $this->delete($path);
    }

    public function delete($path)
    {
        $object = $this->applyPathPrefix($path);

        try {
            $this->client->deleteObject($this->bucket, $object);
        } catch (OssException $e) {
            Log::error(__FUNCTION__, $e);
            return false;
        }
        return !$this->has($path);
    }

    public function deleteDir($dirname)
    {
        $dirObjects = $this->listDirObjects($dirname, true);
        if (count($dirObjects['objects']) > 0) {
            foreach ($dirObjects['objects'] as $object) {
                $objects[] = $object['Key'];
            }
            try {
                $this->client->deleteObjects($this->bucket, $objects);
            } catch (OssException $e) {
                Log::error(__FUNCTION__, $e);
                return false;
            }
        }
        try {
            $this->client->deleteObject($this->bucket, $dirname);
        } catch (OssException $e) {
            Log::error(__FUNCTION__, $e);
            return false;
        }
        return true;
    }

    public function createDir($dirname, Config $config)
    {
        // TODO: Implement createDir() method.
    }

    public function getMetadata($path)
    {
        $object = $this->applyPathPrefix($path);
        try {
            $objectMeta = $this->client->getObjectMeta($this->bucket, $object);
        } catch (OssException $e) {
            Log::error(__FUNCTION__, $e);
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

    public function has($path)
    {
        $object = $this->applyPathPrefix($path);
        return $this->client->doesObjectExist($this->bucket, $object);
    }

    public function listContents($directory = '', $recursive = false)
    {
        // TODO: Implement listContents() method.
    }

    public function getTimestamp($path)
    {
        if ($object = $this->getMetadata($path)) {
            $object['timestamp'] = strtotime($object['last-modified']);
        }
        return $object;
    }

    public function setVisibility($path, $visibility)
    {
        // TODO: Implement setVisibility() method.
    }

    public function getVisibility($path)
    {
        $object = $this->applyPathPrefix($path);
        try {
            $acl = $this->client->getObjectAcl($this->bucket, $object);
        } catch (OssException $e) {
            Log::error(__FUNCTION__, $e);
            return false;
        }

        if ($acl == OssClient::OSS_ACL_TYPE_PUBLIC_READ) {
            $res['visibility'] = AdapterInterface::VISIBILITY_PUBLIC;
        } else {
            $res['visibility'] = AdapterInterface::VISIBILITY_PRIVATE;
        }
        return $res;
    }
}
