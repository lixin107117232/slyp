<?php

namespace app\common\model\seckill;

use think\Model;
use traits\model\SoftDelete;

class Goods extends Model
{

    use SoftDelete;

    

    // 表名
    protected $name = 'seckill_goods';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = 'deletetime';
    protected $startTime = 'starttime';

    // 追加属性
    protected $append = [
        'status_text',
        'specs_data_text'
    ];
    

    protected static function init()
    {
        self::afterInsert(function ($row) {
            $pk = $row->getPk();
            $row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
        });
    }

    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2')];
    }

    public function getSpecsDataList()
    {
        return ['0' => __('Specs_data 0'), '1' => __('Specs_data 1')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getSpecsDataTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['specs_data']) ? $data['specs_data'] : '');
        $list = $this->getSpecsDataList();
        return isset($list[$value]) ? $list[$value] : '';
    }




    public function allgoods()
    {
        return $this->belongsTo('app\common\model\Goods', 'goods_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
