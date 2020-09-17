<?php
/**
 * Created by PhpStorm.
 * Date: 2020/9/16
 * Time: 11:35
 */

namespace TELstatic\Rakan\Plugins;

use League\Flysystem\Plugin\AbstractPlugin;

/**
 * Base64字符串传文件.
 */
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
