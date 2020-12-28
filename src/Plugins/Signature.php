<?php

namespace TELstatic\Rakan\Plugins;

use League\Flysystem\Plugin\AbstractPlugin;

/**
 * 上传策略.
 */
class Signature extends AbstractPlugin
{
    public function getMethod()
    {
        return 'signature';
    }

    public function handle($file)
    {
        return $this->filesystem->getAdapter()->signature($file);
    }
}
