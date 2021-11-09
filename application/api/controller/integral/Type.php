<?php

namespace app\api\controller\integral;

use app\common\controller\Api;
use app\common\model\integral\Type as TypeModel;
use fast\Tree;
use think\Db;
/**
 * 积分商城分类接口
 */
class Type extends Api
{
    protected $noNeedLogin = ['index', 'test1', 'indexV2','getTypeList','getTowChildList','getMenuList'];
    protected $noNeedRight = ['test2'];
    /**
     * 全部分类接口
     **/
    public function index()
    {
        $list=TypeModel::order("weigh desc")->where(['status'=>'normal'])-> limit(9)-> select();
       // ->paginate(8,'','');
        $this->success('返回成功', $list);
    }

    /**
     * 获取一级类目
     **/
    public function getMenuList()
    {
        $list=TypeModel::field('id,name')->where(['status'=>'normal','pid'=>0]) ->order("weigh desc")-> select();
        // ->paginate(8,'','');
        $this->success('返回成功', $list);
    }

    /**
     * 获取指定类目
     * @param int $id 参数
     * @return array
     **/


    public function getTypeList($id = 0){

        // 必须将结果集转换为数组
        $typeList = \think\Db::name("integral_type")->field('id,name,image,pid')->where(['status'=>'normal'])->order('weigh DESC,id ASC')->select();
        Tree::instance()->init($typeList);
        $typeList =Tree::instance()->getTreeArray($id);
        $this->success('返回成功', $typeList);
    }


}
