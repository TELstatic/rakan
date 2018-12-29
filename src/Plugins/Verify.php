<?php

namespace TELstatic\Rakan\Plugins;

use League\Flysystem\Plugin\AbstractPlugin;

/**
 * 回调校验
 */
class Verify extends AbstractPlugin
{
    public function getMethod()
    {
        return 'verify';
    }

    public function handle()
    {
        return $this->filesystem->getAdapter()->verify();
    }
}