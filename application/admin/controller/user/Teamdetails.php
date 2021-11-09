<?php

namespace app\admin\controller\user;

use app\admin\model\UserAchievement;
use app\common\controller\Backend;
use fast\Tree;

/**
 * 会员管理
 *
 * @icon fa fa-user
 */
class Teamdetails extends Backend
{
    
    /**
     * Teamdetails模型对象
     * @var \app\admin\model\Teamdetails
     */
    protected $model = null;
    protected $searchFields = 'fictitious_id,mobile';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Teamdetails;

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
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->where($where)
                ->order('id asc')
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $k => $v) {
                $v->visible(['id', 'fictitious_id', 'username', 'nickname', 'mobile',
                    'p_id','level']);
            }
            $arr = $list->items();
            $new_arr = [];
            foreach (\GuzzleHttp\json_decode(\GuzzleHttp\json_encode($arr),true) as $k => $v){
                $new_arr[$k] = array_merge(\GuzzleHttp\json_decode($arr[$k],true),$this -> myteam_achievement($v['id']));
            }

            $result = array("total" => $list->total(), "rows" => $new_arr);

            return json($result);
        }
        return $this->view->fetch();
    }


    /**
     * 团队详情
     *
     */
    public function detail($ids = null){

        $this->redirect('user/Achievement/index', ['user_id' => $ids]);

    }

    /**
     * 会员团队业绩详情
     *
    */
    public function myteam_achievement($user_id){
        // 查询会员信息
        $user = \app\admin\model\User::where(['id' => $user_id])
            -> field('id')
            -> find();
        // 用户个人本月消费
        $user_sum_money = UserAchievement::where(['pid'=> 0,"user_id"=>$user['id']])
            -> whereTime('createtime','month')
            -> sum('money');
        // 查询用户上个月总消费
        $user_lastmonth_money = UserAchievement::where(['pid'=> 0,"user_id"=>$user['id']])
            -> whereTime('createtime','last month')
            -> sum('money');
        // 查询团队总消费
        $team_sum_money = UserAchievement::where(['pid'=> $user['id']])
            -> whereTime('createtime','month')
            -> sum('money');
        // 查询团队上个月总消费
        $team_lastmonth_money = UserAchievement::where(['pid'=> $user['id']])
            -> whereTime('createtime','last month')
            -> sum('money');


        $data = [
            'user_sum_money' => $user_sum_money,
            'user_lastmonth_money' => $user_lastmonth_money,
            'team_sum_money' => $team_sum_money,
            'team_lastmonth_money' => $team_lastmonth_money,
        ];

        return $data;
    }


}
