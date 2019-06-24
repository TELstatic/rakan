<?php

namespace TELstatic\Rakan;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;

class RakanAdapter extends AbstractAdapter
{
    protected $gateway;

    public function __construct($gateway = 'oss')
    {
        $this->gateway = $gateway;
    }

    public function signature($path)
    {
        return app('rakan.'.$this->gateway)->signature($path);
    }

    public function base64($path, $data)
    {
        return app('rakan.'.$this->gateway)->base64($path, $data);
    }

    public function write($path, $contents, Config $config)
    {
        return app('rakan.'.$this->gateway)->write($path, $contents, $config);
    }

    public function writeStream($path, $resource, Config $config)
    {
        return app('rakan.'.$this->gateway)->writeStream($path, $resource, $config);
    }

    public function update($path, $contents, Config $config)
    {
        return app('rakan.'.$this->gateway)->update($path, $contents, $config);
    }

    public function updateStream($path, $resource, Config $config)
    {
        return app('rakan.'.$this->gateway)->updateStream($path, $resource, $config);
    }

    public function read($path)
    {
        return app('rakan.'.$this->gateway)->read($path);
    }

    public function readStream($path)
    {
        return app('rakan.'.$this->gateway)->readStream($path);
    }

    public function copy($path, $newpath)
    {
        return app('rakan.'.$this->gateway)->copy($path, $newpath);
    }

    public function rename($path, $newpath)
    {
        return app('rakan.'.$this->gateway)->rename($path, $newpath);
    }

    public function delete($path)
    {
        return app('rakan.'.$this->gateway)->delete($path);
    }

    public function deleteDir($dirname)
    {
        return app('rakan.'.$this->gateway)->deleteDir($dirname);
    }

    public function createDir($dirname, Config $config)
    {
        return app('rakan.'.$this->gateway)->createDir($dirname, $config);
    }

    public function getMetadata($path)
    {
        return app('rakan.'.$this->gateway)->getMetadata($path);
    }

    public function getSize($path)
    {
        return app('rakan.'.$this->gateway)->getSize($path);
    }

    public function getMimetype($path)
    {
        return app('rakan.'.$this->gateway)->getMimetype($path);
    }

    public function has($path)
    {
        return app('rakan.'.$this->gateway)->has($path);
    }

    public function listContents($directory = '', $recursive = false)
    {
        return app('rakan.'.$this->gateway)->listContents($directory, $recursive);
    }

    public function getTimestamp($path)
    {
        return app('rakan.'.$this->gateway)->getTimestamp($path);
    }

    public function setVisibility($path, $visibility)
    {
        return app('rakan.'.$this->gateway)->setVisibility($path, $visibility);
    }

    public function policy()
    {
        return app('rakan.'.$this->gateway)->policy();
    }

    public function verify()
    {
        return app('rakan.'.$this->gateway)->verify();
    }

    public function getVisibility($path)
    {
        return app('rakan.'.$this->gateway)->getVisibility($path);
    }

    public function getUrl($path)
    {
        return app('rakan.'.$this->gateway)->getUrl($path);
    }
}
