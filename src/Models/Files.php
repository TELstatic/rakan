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
        'checked', 'url', 'order', 'thumb'
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
            return config('rakan.oss.host') . "/" . $this->path;
        } else {
            return null;
        }
    }

    public function getThumbAttribute()
    {
        if ($this->type == 'file') {
            if (config('rakan.oss.style.thumb')) {
                return config('rakan.oss.host') . "/" . $this->path . '?x-oss-process=style/' . config('rakan.oss.style.thumb');
            } else {
                return config('rakan.oss.host') . "/" . $this->path;
            }
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