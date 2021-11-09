<?php

namespace app\common\model\integral;

use think\Model;
use traits\model\SoftDelete;

class Exchange extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'integral_exchange';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'status_text',
        'mode_text'
    ];
    

    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2'), '3' => __('Status 3'), '4' => __('Status 4'), '5' => __('Status 5'), '6' => __('Status 6'), '8' => __('Status 8')];
    }

    public function getModeList()
    {
        return ['0' => __('Mode 0'), '1' => __('Mode 1')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getModeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['mode']) ? $data['mode'] : '');
        $list = $this->getModeList();
        return isset($list[$value]) ? $list[$value] : '';
    }




    public function user()
    {
        return $this->belongsTo('app\common\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
