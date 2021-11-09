<?php

namespace app\common\model\integral;

use think\Model;


class IntegralSku extends Model
{

    public function Integralattrval()
    {
        return $this->hasMany(IntegralAttrVal::class, 'attr_key_id', 'attr_key_id');
    }
}
