<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use fast\Tree;

/**
 * 商品规格管理
 *
 * @icon fa fa-circle-o
 */
class Specs extends Backend
{

    /**
     * Specs模型对象
     * @var \app\common\model\Specs
     */
    protected $model = null;
    protected $rulelist = [];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\common\model\Specs;
        $ruleList = collection($this->model->where(["pid"=>0])->select())->toArray();
        unset($v);
        /*Tree::instance()->init($ruleList);*/
        $ruledata = [0 => __('None')];
        foreach ($ruleList as $k => &$v) {
            $ruledata[$v['id']] = $v['name'];
        }
        $this->view->assign('ruledata', $ruledata);

    }

    public function import()
    {
        parent::import();
    }

}
