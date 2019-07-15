<?php

namespace TELstatic\Rakan\Plugins;

use League\Flysystem\Plugin\AbstractPlugin;

/**
 * 上传策略.
 */
class Policy extends AbstractPlugin
{
    public function getMethod()
    {
        return 'policy';
    }

    public function handle($route = 'rakan.callback')
    {
        return $this->filesystem->getAdapter()->policy($route);
    }
}
