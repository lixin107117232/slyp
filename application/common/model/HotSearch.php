<?php

namespace app\common\model;

use think\Model;
use traits\model\SoftDelete;

class HotSearch extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'hospital_search';
    
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
