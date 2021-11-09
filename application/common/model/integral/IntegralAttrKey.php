<?php

namespace app\common\model\integral;

use think\Model;


class IntegralAttrKey extends Model
{

    public function Integralattrval()
    {
        return $this->hasMany(IntegralAttrVal::class, 'attr_key_id', 'attr_key_id');
    }

}
