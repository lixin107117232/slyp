<?php

namespace app\common\model\seckill;

use think\Model;


class SeckillSku extends Model
{

    public function Seckillattrval()
    {
        return $this->hasMany(SeckillAttrVal::class, 'attr_key_id', 'attr_key_id');
    }
}
