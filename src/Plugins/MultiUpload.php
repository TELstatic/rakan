<?php
/**
 * Created by PhpStorm.
 * User: Sakuraiyaya
 * Date: 2020/9/9
 * Time: 8:42.
 */

namespace TELstatic\Rakan\Plugins;

use League\Flysystem\Plugin\AbstractPlugin;

class MultiUpload extends AbstractPlugin
{
    public function getMethod()
    {
        return 'multiUpload';
    }

    public function handle($path, $file, $options = [])
    {
        return $this->filesystem->getAdapter()->multiUpload($path, $file, $options);
    }
}
