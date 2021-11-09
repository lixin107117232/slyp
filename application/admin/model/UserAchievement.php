<?php

namespace app\admin\model;

use think\Model;

class UserAchievement extends Model
{

    // 表名
    protected $name = 'user_achievement';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    // 追加属性
    protected $append = [
    ];

    // 连接用户表
    public function userdata()
    {
        return $this->belongsTo('app\common\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

}
