<?php

namespace TELstatic\Rakan\Interfaces;

use League\Flysystem\Config;

interface GatewayApplicationInterface
{
    public function getPath($file);

    public function signature($file);

    public function policy();

    public function verify();

    public function write($path, $contents, Config $config);

    public function writeStream($path, $resource, Config $config);

    public function update($path, $contents, Config $config);

    public function updateStream($path, $resource, Config $config);

    public function rename($path, $newpath);

    public function copy($path, $newpath);

    public function delete($path);

    public function deleteDir($dirname);

    public function createDir($dirname, Config $config);

    public function setVisibility($path, $visibility);

    public function getVisibility($path);

    public function has($path);

    public function read($path);

    public function readStream($path);

    public function listContents($dirname);

    public function getUrl($path);
}
