<?php

namespace app\common\model;

use think\Model;

class UserMoneyLog extends Model
{

    // 表名
    protected $name = 'user_money_log';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    // 追加属性
    protected $append = [
    ];

}
