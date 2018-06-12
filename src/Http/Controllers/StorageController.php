<?php

namespace TELstatic\Rakan\Controller;

use TELstatic\Rakan\Models\Storages;

class StorageController extends Controller
{

    public $storage;

    public function __construct(Storages $storages)
    {
        $this->storage = $storages;
    }

    public function index()
    {

        $code = [
            'fun' => __FUNCTION__
        ];

        return responseData(200, $code);
    }


}