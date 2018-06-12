<?php

namespace TELstatic\Rakan\Events;

use Illuminate\Database\Eloquent\Model;
use TELstatic\Rakan\Models\Files;

abstract class BaseEvent
{
    protected $model;

    protected $action;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function getAction()
    {
        return $this->action;
    }

}