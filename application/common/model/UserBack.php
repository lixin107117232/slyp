<?php

namespace app\common\model;

use think\Model;

class UserBack extends Model
{

    // 表名
    protected $name = 'user_back';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
    ];


    // 连接银行表
    public function backdata(){
        return $this->belongsTo('app\common\model\Back', 'back_code', 'code', [], 'LEFT')->setEagerlyType(0);
    }
}
