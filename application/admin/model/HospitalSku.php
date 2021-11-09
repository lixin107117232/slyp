<?php

namespace app\admin\model;

use app\common\model\MoneyLog;
use app\common\model\ScoreLog;
use think\Model;

class HospitalSku extends Model
{
    // 表名
    protected $name = 'hospital_sku';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名

    // 追加属性
    protected $append = [
    ];
}
