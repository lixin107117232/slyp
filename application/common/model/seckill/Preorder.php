<?php

namespace app\common\model\seckill;

use think\Model;
use traits\model\SoftDelete;

class Preorder extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'pre_order';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'order_status_text',
        'status_text',
        'pay_data_text',
        'paytime_text',
        'start_time_text',
        'end_time_text',
        'pay_time_text',
    ];
    

    
    public function getOrderStatusList()
    {
        return ['0' => __('Order_status 0'), '1' => __('Order_status 1'), '2' => __('Order_status 2')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '2' => __('Status 2'), '1' => __('Status 1'), '3' => __('Status 3'), '4' => __('Status 4')];
    }

    public function getPayDataList()
    {
        return [ '1' => __('Pay_data 1'), '2' => __('Pay_data 2'), '3' => __('Pay_data 3')];
    }


    public function getOrderStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['order_status']) ? $data['order_status'] : '');
        $list = $this->getOrderStatusList();
        return isset($list[$value]) ? $list[$value] : '';
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


    public function getStartTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['start_time']) ? $data['start_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getEndTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['end_time']) ? $data['end_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setPaytimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setStartTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setEndTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }
    public function user()
    {
        return $this->belongsTo('app\common\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

}
