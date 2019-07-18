<?php

namespace TELstatic\Rakan\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Rakan extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('rakan.default.table_name'));
    }

    const RAKAN_ACL_TYPE_PRIVATE = 0;
    const RAKAN_ACL_TYPE_PUBLIC_READ = 1;
    const RAKAN_ACL_TYPE_PUBLIC_READ_WRITE = 2;

    public static $rakanACLTypeMap = [
        self::RAKAN_ACL_TYPE_PRIVATE           => 'private',
        self::RAKAN_ACL_TYPE_PUBLIC_READ       => 'public',
        self::RAKAN_ACL_TYPE_PUBLIC_READ_WRITE => 'public_read_write',
    ];

    protected $guarded = [];

    protected $appends = [
        'url',
        'checked',
        'order',
    ];

    public function getUrlAttribute()
    {
        if ($this->type === 'file') {
            if ($this->attributes['visible'] === self::RAKAN_ACL_TYPE_PRIVATE) {
                $url = Storage::disk($this->gateway ?? 'oss')->signature($this->path);
            } else {
                $url = rtrim($this->host, '/').'/'.$this->path;
            }

            return $this->removeSchemes($url);
        } else {
            return '';
        }
    }

    /**
     * 选中状态.
     */
    public function getCheckedAttribute()
    {
        return false;
    }

    /**
     * 排序.
     */
    public function getOrderAttribute()
    {
        return 0;
    }

    protected function removeSchemes($url)
    {
        $replaceList = [
            'http:',
            'https:',
        ];

        return str_replace($replaceList, '', $url);
    }
}
