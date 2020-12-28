<?php

namespace TELstatic\Rakan\Plugins;

use League\Flysystem\Plugin\AbstractPlugin;

class GetPath
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