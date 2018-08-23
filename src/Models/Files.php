<?php

namespace TELstatic\Rakan\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class Files extends Model
{
    protected $primaryKey = '_id';
    protected $connection = 'mongodb';
    protected $collection = 'files';

    protected $guarded = [];

    protected $appends = [
        'checked', 'url', 'order'
    ];

    /**
     * 选中状态
     */
    public function getCheckedAttribute()
    {
        return false;
    }

    /**
     * src
     */
    public function getUrlAttribute()
    {
        if ($this->type == 'file') {
            return config('rakan.alioss.host') . "/" . $this->path;
        } else {
            return null;
        }
    }

    /**
     * 排序
     */
    public function getOrderAttribute()
    {
        return 0;
    }

}