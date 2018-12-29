<?php

namespace TELstatic\Rakan\Models;

use Illuminate\Database\Eloquent\Model;

class Rakan extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('rakan.default.table_name'));
    }

    protected $guarded = [];

    protected $appends = [
        'url', 'checked', 'order',
    ];

    public function getUrlAttribute()
    {
        if ($this->type === 'file') {
            return rtrim($this->host, '/').'/'.$this->path;
        } else {
            return null;
        }
    }

    /**
     * 选中状态
     */
    public function getCheckedAttribute()
    {
        return false;
    }

    /**
     * 排序
     */
    public function getOrderAttribute()
    {
        return 0;
    }
}
