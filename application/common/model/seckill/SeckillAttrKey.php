<?php

namespace app\common\model\seckill;

use think\Model;


class SeckillAttrKey extends Model
{

    public function seckillattrval()
    {
        return $this->hasMany(SeckillAttrVal::class, 'attr_key_id', 'attr_key_id');
    }

}
