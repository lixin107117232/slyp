<?php

namespace app\admin\controller\hospital;

use app\admin\model\User;
use app\admin\model\UserAchievement;
use app\common\controller\Backend;
use app\common\model\Config;
use app\common\model\UserBonusLog;
use think\Db;
use think\Exception;

/**
 * 医美订单管理
 *
 * @icon fa fa-circle-o
 */
class Order extends Backend
{
    
    /**
     * Order模型对象
     * @var \app\admin\model\Order
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Order;

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
                ->with(['userdata'])
                ->where($where)
                ->order($sort, $order)
                ->order("createtime", "desc")
                ->paginate($limit);
            foreach ($list as $k => $v) {
                $v->visible(['id','order_no','user_id','hospital_goods_name','sku_str','all_money',
                    'actual_money','num','status','pay_status','createtime','paytime','updatetime','deletetime']);
                $v->visible(['userdata']);
                $v->getRelation('userdata')->visible(['username']);

                if($v -> status == 0){
                    $v -> status = "待支付";
                }else if($v -> status == 1){
                    $v -> status = "待使用";
                }else if($v -> status == 2){
                    $v -> status = "已完成";
                }else if($v -> status == 3){
                    $v -> status = "已取消";
                }
                if($v -> pay_status == 0){
                    $v -> pay_status = "未支付";
                }else if($v -> pay_status == 1){
                    $v -> pay_status = "余额";
                }else if($v -> pay_status == 2){
                    $v -> pay_status = "微信";
                }else if($v -> pay_status == 3){
                    $v -> pay_status = "支付宝";
                }
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 核销
     *
    */
    public function write_off($ids = null)
    {
        $order_data = $this->model->get($ids);
        if (!$order_data) {
            $this->error(__('No Results were found'));
        }
        // 判断用户是否支付该订单
        if($order_data['paytime'] == ""){
            $this->error(__('Order not paid'));
        }
        // 开启事务,避免出现垃圾数据
        Db::startTrans();
        try {

            /* 分红（核销） start */
            $this->user_write_off($order_data['user_id'],$order_data['actual_money']);

            /* 分红（核销） end */

            // 修改核销状态
            $order_data -> updatetime = time();
            $order_data -> status = 2;
            $order_data -> save();

            Db::commit();
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            Db::rollback();
            $this -> error(__('Failure of general agent is self purchase bonus'));
        }



        $this->success(__('Write off successful'));
    }


    // 递归获取上级ID
    public function getPraent($user_id){
        $arr=array();
        // 获取当前用户信息
        $now_userdata = User::where('id',$user_id) -> find();
        // 获取当前用户的父类
        $parent = User::where('id',$now_userdata['p_id']) -> find();
        if($now_userdata['p_id'] > 0){
            $arr[]=$parent['id'];
            $arr=array_merge($this->getPraent($parent['id']),$arr);
        }
        return $arr;
    }

    /**
     * 添加日志
     * $user_id 用户id
     * $bonus   变更金额
     * $mome    备注
     * $type    '+'or'-'
    */
    public function add_log($user_id,$bonus,$mome,$type){
        $user_data = User::where('id',$user_id) -> find();
        $data = [
            'user_id' => $user_id,
            'bonus' => $bonus,
            'before' => $user_data['bonus'],
            'memo' => $mome,
            'createtime' => time(),
        ];
        if($type == '+'){
            $data['after'] = $user_data['bonus'] + $bonus;
        }else{
            $data['after'] = $user_data['bonus'] - $bonus;
        }
        UserBonusLog::insert($data);
    }

    /**
     * 封装的分销方法
     * $user_id 用户id
     * $actual_money   实际支付金额
     *
     */
    public function user_write_off($user_id,$actual_money){
        $config = new Config();
        // 总代理自购分红比率
        $one_own = $config -> getHospitalZongZigou();
        // 代理商自购分红比率
        $two_own = $config -> getHospitalDaiZigou();

        // 代理商平级奖分红比率
        $two_pingji = $config -> getHospitalDaiPingji();
        // 代理商对总代理上级奖比率
        $dai_zong = $config -> getHospitalDaiZong();
        // 消费者对总代理跨级将比率
        $xiao_zong = $config -> getHospitalXiaoZong();
        // 消费者对代理商上级奖比率
        $xiao_dai = $config -> getHospitalXiaoDai();
        // 消费者对代理商对总代理上上级奖比率
        $xiao_dai_zong = $config -> getHospitalXiaoDaiZong();
        if(!$user_id){
            return false;
        }
        if(!$actual_money){
            return false;
        }

        // 获取当前用户信息
        $user_data = User::where('id',$user_id) -> find();

        // 获取所有上级用户
        $parent_id = array_reverse($this->getPraent($user_id));

        /* 分红 start */
        // 判断当前用户等级，消费者无自购分红奖励，代理商自购30%分红奖励，总代理40%分红奖励
        // 如果当前用户是消费者
        if($user_data['level'] == 1){
            // 代理商自购分红
            try {
                // 消费者无自购分红奖励
                // 添加业绩明细
                $this->add_achievement("0",$user_id,$actual_money);

                // 判断该用户是否有上级，如果没有上级则不进行循环
                if(count($parent_id) != 0){
                    // 如果存在上级
                    // 首先循环一次所有上级，查看所有上级中是否同时存在代理商和总代理
                    $num = 0;
                    $zong_id = 0;
                    foreach ($parent_id as $k => $v){
                        // 上级用户数据
                        $shangji_user_data = User::where('id',$v) -> find();
                        // 判断是否有代理商
                        if($shangji_user_data['level'] == 2){
                            // 存在代理商
                            $num = 1;
                        }
                        // 判断是否有总代理
                        if($shangji_user_data['level'] == 3){
                            // 存在总代理
                            $num = 2;
                            $zong_id = $shangji_user_data['id'];
                            break;
                        }
                    }
                    // 循环所有上级
                    foreach ($parent_id as $k => $v){
                        // 上级用户数据
                        $shangji_user_data = User::where('id',$v) -> find();
                        // 判断用户上级是否为总代理
                        if($shangji_user_data['level'] == 3){
                            // 如果上级为总代理，上级享受跨级分红
                            $new_money = $actual_money * $xiao_zong;
                            $shangji_user_data['bonus'] = $shangji_user_data['bonus'] + $new_money;

                            // 添加记录
                            $this->add_log($shangji_user_data['id'],$new_money,'佣金','+');
                            $shangji_user_data -> save();
                            // 添加业绩明细
                            $this->add_achievement($shangji_user_data['id'],$user_id,$actual_money);

                            // 上级代理商获得分红之后，结束循环，跳出循环
                            break;
                        }

                        // 判断用户上级是否为代理商
                        if($shangji_user_data['level'] == 2){
                            // 上级为代理商，且无总代理(消费者 -> 代理商)上级奖
                            $new_money = $actual_money * $xiao_dai;
                            $shangji_user_data['bonus'] = $shangji_user_data['bonus'] + $new_money;

                            // 添加记录
                            $this->add_log($shangji_user_data['id'],$new_money,'佣金','+');
                            $shangji_user_data -> save();

                            // 添加业绩明细
                            $this->add_achievement($shangji_user_data['id'],$user_id,$actual_money);

                            // 判断用户是否存在总代理
                            if($num == 2){
                                // 用户上级为代理商，并且上上级拥有总代理，则总代理获得1%的分红
                                if($zong_id != 0){
                                    // 查询总代理信息
                                    $zong_data = User::where('id',$zong_id) -> find();
                                    // 上级为代理商，且无总代理(消费者 -> 代理商)上级奖
                                    $new_money = $actual_money * $xiao_dai_zong;
                                    $zong_data['bonus'] = $zong_data['bonus'] + $new_money;

                                    // 添加记录
                                    $this->add_log($zong_data['id'],$new_money,'佣金','+');
                                    $zong_data -> save();
                                    // 添加业绩明细
                                    $this->add_achievement($zong_data['id'],$user_id,$actual_money);
                                }
                            }

                            // 获得分红之后，结束循环，跳出循环
                            break;

                        }



                    }
                }

                Db::commit();
            } catch (Exception $e) {
                $this->setError($e->getMessage());
                Db::rollback();
                $this -> error(__('Failure of agent is self purchase bonus'));
            }
        }

        // 如果当前用户是代理商
        if($user_data['level'] == 2){
            // 代理商自购分红
            try {
                // 自购分红金额
                $two_own_money = $actual_money * $two_own;
                $user_data['bonus'] =  $user_data['bonus']+$two_own_money;

                // 添加记录
                $this->add_log($user_data['id'],$two_own_money,'佣金','+');
                $user_data -> save();

                // 添加业绩明细
                $this->add_achievement("0",$user_id,$actual_money);

                // 判断该用户是否有上级，如果没有上级则不进行循环
                if(count($parent_id) != 0){
                    // 如果存在上级
                    // 首先循环一次所有上级，查看所有上级中是否同时存在代理商和总代理
                    $num = 0;
                    $zong_id = 0;
                    foreach ($parent_id as $k => $v){
                        // 上级用户数据
                        $shangji_user_data = User::where('id',$v) -> find();
                        // 判断是否有代理商
                        if($shangji_user_data['level'] == 2){
                            // 存在代理商
                            $num = 1;
                        }
                        // 判断是否有总代理
                        if($shangji_user_data['level'] == 3){
                            // 存在总代理
                            $num = 2;
                            $zong_id = $shangji_user_data['id'];
                            break;
                        }
                    }
                    // 上级代理商 1%截至（平级奖）
                    // 循环所有上级，如果存在代理商平级奖，则拥有分红1%
                    foreach ($parent_id as $k => $v){
                        // 上级用户数据
                        $shangji_user_data = User::where('id',$v) -> find();
                        // 判断上级用户是否为代理商或者总代理
                        if($shangji_user_data['level'] == 2){
                            // 上级为代理商（平级奖）
                            // 分红金额
                            $shangji_fenhong_money = $actual_money * $two_pingji;
                            $shangji_user_data['bonus'] = $shangji_user_data['bonus'] + $shangji_fenhong_money;

                            // 添加记录
                            $this->add_log($shangji_user_data['id'],$shangji_fenhong_money,'佣金','+');
                            $shangji_user_data -> save();

                            // 添加业绩明细
                            $this->add_achievement($shangji_user_data['id'],$user_id,$actual_money);

                            if($num == 2){
                                // 用户上级为代理商，并且上上级拥有总代理，则总代理获得1%的分红
                                if($zong_id != 0){
                                    // 查询总代理信息
                                    $zong_data = User::where('id',$zong_id) -> find();
                                    // 上级为代理商，且无总代理(消费者 -> 代理商)上级奖
                                    $new_money = $actual_money * $xiao_dai_zong;
                                    $zong_data['bonus'] = $zong_data['bonus'] + $new_money;

                                    // 添加记录
                                    $this->add_log($zong_data['id'],$new_money,'佣金','+');
                                    $zong_data -> save();

                                    // 添加业绩明细
                                    $this->add_achievement($zong_data['id'],$user_id,$actual_money);
                                }
                            }
                            // 上级代理商获得分红之后，结束循环，跳出循环
                            break;
                        }

                        if($shangji_user_data['level'] == 3){
                            // 上级为总代理（上级）
                            // 分红金额
                            $shangji_fenhong_money = $actual_money * $dai_zong;
                            $shangji_user_data['bonus'] = $shangji_user_data['bonus'] + $shangji_fenhong_money;
                            $shangji_user_data -> save();

                            // 添加记录
                            $this->add_log($shangji_user_data['id'],$shangji_fenhong_money,'佣金','+');


                            // 添加业绩明细
                            $this->add_achievement($shangji_user_data['id'],$user_id,$actual_money);
                            // 总代理获得分红之后，结束循环，跳出循环
                            break;
                        }
                    }
                }


                Db::commit();
            } catch (Exception $e) {
                $this->setError($e->getMessage());
                Db::rollback();
                $this -> error(__('Failure of agent is self purchase bonus'));
            }

        }

        // 如果当前用户是总代理
        if($user_data['level'] == 3){
            // 总代理自购分红
            try {
                // 分红金额
                $one_own_money = $actual_money*$one_own;
                $user_data['bonus'] =  $user_data['bonus']+$one_own_money;


                // 添加记录
                $this->add_log($user_data['id'],$one_own_money,'佣金','+');
                $user_data -> save();

                // 添加业绩明细
                $this->add_achievement("0",$user_id,$actual_money);
                Db::commit();
            } catch (Exception $e) {
                $this->setError($e->getMessage());
                Db::rollback();
                $this -> error(__('Failure of general agent is self purchase bonus'));
            }

        }
        /* 分红 end */
    }

    /**
     * 添加业绩明细
     * $pid 上级用户id
     * $user_id   当前用户id
     * $actual_money   实际支付金额
     *
    */
    public function add_achievement($pid,$user_id,$actual_money){
        $achievement_data = [
            'pid' => $pid,
            'user_id' => $user_id,
            'money' => $actual_money,
            'createtime' => time(),
        ];
        // 添加业绩
        UserAchievement::insert($achievement_data);
    }

}
