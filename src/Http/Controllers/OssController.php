<?php

namespace TELstatic\Rakan\Controller;

class OssController extends Controller
{

    public function index()
    {

        $code = [
            'fun' => __FUNCTION__
        ];

        return responseData(200, $code);

    }


}
