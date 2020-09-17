<?php

namespace TELstatic\Rakan;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;

class RakanAdapter extends AbstractAdapter
{
    protected $gateway;

    protected $config;

    public function __construct($gateway = 'oss')
    {
        $this->gateway = $gateway;
    }

    public function config($config)
    {
        $this->config = $config;

        return $this;
    }

    public function signature($path)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->signature($path);
    }

    public function base64($path, $data)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->base64($path, $data);
    }

    public function write($path, $contents, Config $config)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->write(ltrim($path, '/'), $contents, $config);
    }

    public function writeStream($path, $resource, Config $config)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->writeStream($path, $resource, $config);
    }

    public function update($path, $contents, Config $config)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->update($path, $contents, $config);
    }

    public function updateStream($path, $resource, Config $config)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->updateStream($path, $resource, $config);
    }

    public function read($path)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->read($path);
    }

    public function readStream($path)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->readStream($path);
    }

    public function copy($path, $newpath)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->copy($path, $newpath);
    }

    public function rename($path, $newpath)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->rename($path, $newpath);
    }

    public function move($path, $newpath)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->rename($path, $newpath);
    }

    public function delete($path)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->delete($path);
    }

    public function deleteDir($dirname)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->deleteDir($dirname);
    }

    public function createDir($dirname, Config $config)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->createDir($dirname, $config);
    }

    public function getMetadata($path)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->getMetadata($path);
    }

    public function getSize($path)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->getSize($path);
    }

    public function getMimetype($path)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->getMimetype($path);
    }

    public function has($path)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->has($path);
    }

    public function exists($path)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->has($path);
    }

    public function listContents($directory = '', $recursive = false)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->listContents($directory, $recursive);
    }

    public function getTimestamp($path)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->getTimestamp($path);
    }

    public function setVisibility($path, $visibility)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->setVisibility($path, $visibility);
    }

    public function policy($route = 'rakan.callback')
    {
        return app('rakan.'.$this->gateway)->config($this->config)->policy($route);
    }

    public function verify()
    {
        return app('rakan.'.$this->gateway)->config($this->config)->verify();
    }

    public function getVisibility($path)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->getVisibility($path);
    }

    public function getUrl($path)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->getUrl($path);
    }

    public function symlink($symlink, $file)
    {
        return app('rakan.'.$this->gateway)->config($this->config)->symlink($symlink, $file);
    }
 
    public function multiUpload($path, $file, $options = [])
    {
        return app('rakan.'.$this->gateway)->config($this->config)->multiUpload($path, $file, $options);
    }
}
