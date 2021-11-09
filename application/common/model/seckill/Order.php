<?php

namespace app\common\model\seckill;

use think\Model;
use traits\model\SoftDelete;

class Order extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'seckill_order';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = 'deletetime';

    // 追加属性
    protected $append = [
        'status_text',
        'ischoice_text',
        'pay_data_text',
        'paytime_text',
        'companytime_text',
        'rgoodstime_text'
    ];
    

    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2'), '3' => __('Status 3'), '4' => __('Status 4'), '5' => __('Status 5'), '6' => __('Status 6'), '7' => __('Status 7'), '8' => __('Status 8')];
    }

    public function getIschoiceList()
    {
        return ['0' => __('Ischoice 0'), '1' => __('Ischoice 1')];
    }

    public function getPayDataList()
    {
        return ['0' => __('Pay_data 0'), '1' => __('Pay_data 1')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIschoiceTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['ischoice']) ? $data['ischoice'] : '');
        $list = $this->getIschoiceList();
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


    public function getCompanytimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['companytime']) ? $data['companytime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getRgoodstimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['rgoodstime']) ? $data['rgoodstime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setPaytimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setCompanytimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setRgoodstimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    public function user()
    {
        return $this->belongsTo('app\common\model\User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
