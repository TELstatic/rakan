<?php

namespace TELstatic\Rakan\Facades;

use Illuminate\Support\Facades\Facade;

class Rakan extends Facade{

    protected static function getFacadeAccessor()
    {
        return 'Rakan';
    }
}