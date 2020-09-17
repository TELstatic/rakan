<?php

namespace TELstatic\Rakan\Facades;

use Illuminate\Support\Facades\Facade;

class Rakan extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'Rakan';
    }

    public static function oss()
    {
        return app('rakan.oss');
    }

    public function qiniu()
    {
        return app('rakan.qiniu');
    }

    public function obs()
    {
        return app('rakan.obs');
    }
  
    public function cos()
    {
        return app('rakan.cos');
    }
}
