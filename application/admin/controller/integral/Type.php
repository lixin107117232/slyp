<?php

namespace app\admin\controller\integral;

use app\common\controller\Backend;
use fast\Tree;
use think\Db;

/**
 * 积分商城分类管理
 *
 * @icon fa fa-circle-o
 */
class Type extends Backend
{

    protected $model = null;
    protected $typeList = [];
    protected $multiFields = 'ismenu,status';

//    protected $model = null;

    /**
     * Type模型对象
     * @var \app\common\model\integral\Type
     */

    public function _initialize()
    {

        parent::_initialize();

        $this->model = model('IntegralType');
        // 必须将结果集转换为数组
        $typeList = \think\Db::name("integral_type")->field('type,condition,remark,createtime,updatetime', true)->order('weigh DESC,id ASC')->select();
        foreach ($typeList as $k => &$v) {
            $v['name'] = __($v['name']);
        }
        unset($v);
        Tree::instance()->init($typeList);

        $this->typeList = Tree::instance()->getTreeList(Tree::instance()->getTreeArray(0), 'name');
        $typedata = [0 => __('None')];


        foreach ($this->typeList as $k => &$v) {
            if (!$v['ismenu']) {
                continue;
            }
            $typedata[$v['id']] = $v['name'];
            unset($v['spacer']);
        }
        unset($v);

        $this->view->assign('ruledata', $typedata);
        $this->view->assign("menutypeList", $this->model->getMenutypeList());



    }

    public function index()
    {


        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            $list = $this->typeList;
            $total = count($this->typeList);
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }


        return $this->view->fetch();
    }

    public function import()
    {
        parent::import();
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {

            $this->token();

            $params = $this->request->post("row/a", [], 'strip_tags');
            if ($params) {
                if (!isset($params['pid'])) {
                    $this->error(__('参数错误'));
                }
                $result = $this->model->insert($params,'true');
                if ($result === false) {
                    $this->error($this->model->getError());
                }
                $this->success();
            }
            $this->error();
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {

        $row = $this->model->get(['id' => $ids]);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a", [], 'strip_tags');
            if ($params) {
                if (!isset($params['pid'])) {
                    $this->error(__('The non-menu rule must have parent'));
                }
                if ($params['pid'] == $row['id']) {
                    $this->error(__('Can not change the parent to self'));
                }
                if ($params['pid'] != $row['pid']) {
                    $childrenIds = Tree::instance()->init(collection(AuthRule::select())->toArray())->getChildrenIds($row['id']);
                    if (in_array($params['pid'], $childrenIds)) {
                        $this->error(__('Can not change the parent to child'));
                    }
                }
                //这里需要针对name做唯一验证
                $typeValidate = \think\Loader::validate('Type');
                $typeValidate->rule([
                    'name' => 'require|format|unique:typeRule,name,' . $row->id,
                ]);

                $result = $row->save($params);
                if ($result === false) {
                    $this->error($row->getError());
                }
                $this->success();
            }
            $this->error();
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * 删除
     */
    public function del($ids = "")
    {
        if (!$this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ? $ids : $this->request->post("ids");
        if ($ids) {
            $delIds = [];
            foreach (explode(',', $ids) as $k => $v) {
                $delIds = array_merge($delIds, Tree::instance()->getChildrenIds($v, true));
            }
            $delIds = array_unique($delIds);

            $count = $this->model->where('id', 'in', $delIds)->delete();
            if ($count) {
                $this->success();
            }
        }
        $this->error();
    }

}
