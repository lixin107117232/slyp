<?php

namespace app\admin\model;

use app\common\model\MoneyLog;
use app\common\model\ScoreLog;
use think\Model;

class IntegralSku extends Model
{
    public function Integralattrval()
    {
        return $this->hasMany(IntegralAttrVal::class, 'attr_key_id', 'attr_key_id');
    }
}
