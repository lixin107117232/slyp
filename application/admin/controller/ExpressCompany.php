<?php

namespace app\admin\controller;

use app\common\controller\Backend;

/**
 * 快递公司管理
 *
 * @icon fa fa-circle-o
 */
class ExpressCompany extends Backend
{
    
    /**
     * ExpressCompany模型对象
     * @var \app\common\model\ExpressCompany
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\common\model\ExpressCompany;

    }

    public function import()
    {
        parent::import();
    }

    

}
