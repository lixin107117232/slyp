<?php

namespace app\common\model\agent;

use think\Model;
use traits\model\SoftDelete;

class GiftOrder extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'agent_gift_order';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'status_text',
        'pay_data_text',
        'paytime_text',
        'cancel_time_text'
    ];
    

    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2')];
    }

    public function getPayDataList()
    {
        return ['0' => __('Pay_data 0'), '1' => __('Pay_data 1'), '2' => __('Pay_data 2')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getPayDataTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['pay_data']) ? $data['pay_data'] : '');
        $list = $this->getPayDataList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getPaytimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['paytime']) ? $data['paytime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getCancelTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['cancel_time']) ? $data['cancel_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setPaytimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setCancelTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
