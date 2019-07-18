<?php
/**
 * Created by PhpStorm.
 * User: TELstatic
 * Date: 2019-07-10
 * Time: 8:36.
 */

namespace TELstatic\Rakan\Plugins;

use League\Flysystem\Plugin\AbstractPlugin;

class Symlink extends AbstractPlugin
{
    public function getMethod()
    {
        return 'symlink';
    }

    public function handle($symlink, $file)
    {
        return $this->filesystem->getAdapter()->symlink($symlink, $file);
    }
}
