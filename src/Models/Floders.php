<?php

namespace TELstatic\Rakan\Models;

use Illuminate\Database\Eloquent\Model;

class Floders extends Model
{

    protected $table = "rakan_floder";

    protected $hidden = [
        'target_id', 'created_at', 'updated_at'
    ];

    protected $appends = [
        'expand', 'title'
    ];

    public function chlid()
    {
        return $this->hasMany('TELstatic\Rakan\Models\Floders', 'pid', 'id');
    }

    public function chlidren()
    {
        return $this->chlid()->with('chlidren');
    }

    public function getExpandAttribute()
    {
        return true;
    }

    public function getTitleAttribute()
    {
        return $this->path;
    }


}