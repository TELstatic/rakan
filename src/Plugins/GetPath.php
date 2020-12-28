<?php

namespace TELstatic\Rakan\Plugins;

use League\Flysystem\Plugin\AbstractPlugin;

/**
 * 获取文件路径.
 *
 * @desc 获取文件路径
 *
 * @author TELstatic
 */
class GetPath extends AbstractPlugin
{
    public function getMethod()
    {
        return 'getPath';
    }

    public function handle($file)
    {
        return $this->filesystem->getAdapter()->getPath($file);
    }
}
