<?php

namespace TELstatic\Rakan\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class Files extends Model
{
    protected $table = "files";

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
        if ($this->type == 'file') {
            return config('rakan.alioss.host') . "/" . $this->path;
        } else {
            return null;
        }
    }
}