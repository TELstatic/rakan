<?php

namespace TELstatic\Rakan\Plugins;

use League\Flysystem\Plugin\AbstractPlugin;

/**
 * 动态配置.
 */
class Config extends AbstractPlugin
{
    public function getMethod()
    {
        return 'config';
    }

    public function handle($config)
    {
        return $this->filesystem->getAdapter()->config($config);
    }
}
