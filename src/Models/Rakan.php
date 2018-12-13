<?php

namespace TELstatic\Rakan\Models;

use Illuminate\Database\Eloquent\Model;

class Rakan extends Model
{
    public $table;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = config('ranka.table.name');
    }

    protected $guarded = [];

    protected $appends = [
        'url', 'checked', 'order',
    ];

    public function getUrlAttribute()
    {
        if ($this->type === 'file') {
            return config('rakan.oss.host') . $this->path;
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