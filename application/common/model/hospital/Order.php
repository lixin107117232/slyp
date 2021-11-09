<?php

namespace app\common\model\hospital;

use think\Model;


class Order extends Model
{
    // 表名
    protected $name = 'hospital_order';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    


    







}
