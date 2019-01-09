<?php

namespace TELstatic\Rakan\Plugins;

use League\Flysystem\Plugin\AbstractPlugin;
use League\Flysystem\Config;

/**
 * Base64字符串传文件.
 */
class Base64 extends AbstractPlugin
{
    public function getMethod()
    {
        return 'base64';
    }

    public function handle($path, $data)
    {
        return $this->filesystem->getAdapter()->base64($path, $data);
    }
}