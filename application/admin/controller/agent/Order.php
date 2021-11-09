<?php

namespace app\admin\controller\agent;

use app\common\controller\Backend;

/**
 * 代理商购买记录
 *
 * @icon fa fa-circle-o
 */
class Order extends Backend
{
    
    /**
     * GiftOrder模型对象
     * @var \app\common\model\agent\GiftOrder
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\common\model\agent\GiftOrder;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("payDataList", $this->model->getPayDataList());
    }


}
