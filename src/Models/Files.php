<?php

namespace TELstatic\Rakan\Models;

use Illuminate\Database\Eloquent\Model;
use TELstatic\Rakan\Events\FileCreated;
use TELstatic\Rakan\Events\FileDeleted;

class Files extends Model
{
    protected $table = "rakan_file";

    protected $guarded = [];

    protected $appends = [
        'checked', 'url'
    ];

    /**
     * 选中属性
     */
    public function getCheckedAttribute()
    {
        return false;
    }


    /**
     * src 属性
     */
    public function getUrlAttribute()
    {
        return config('rakan.alioss.host') . "/" . $this->path;
    }

    /**
     * 触发事件 增减使用空间
     */
    protected $dispatchesEvents = [
        'created' => FileCreated::class,
        'deleted' => FileDeleted::class,
    ];

}