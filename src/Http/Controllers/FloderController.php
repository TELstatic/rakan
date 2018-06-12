<?php

namespace TELstatic\Rakan\Controller;

use TELstatic\Rakan\Models\Floders;
use TELstatic\Rakan\Rakan;

class FloderController extends Controller
{

    public $floder;

    public function __construct(Floders $floders)
    {
        $this->floder = $floders;
    }

    public function index()
    {
        return Rakan::getFloders();
    }

    public function destory($id)
    {

    }

    public function store()
    {

    }

    public function update($id)
    {
        dump($id);
    }




}
