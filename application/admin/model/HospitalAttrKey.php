<?php

namespace app\admin\model;

use app\common\model\MoneyLog;
use app\common\model\ScoreLog;
use think\Model;

class HospitalAttrKey extends Model
{
    public function Hospitalattrval()
    {
        return $this->hasMany(HospitalAttrVal::class, 'attr_key_id', 'attr_key_id');
    }

}
