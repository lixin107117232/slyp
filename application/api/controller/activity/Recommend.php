<?php

namespace app\api\controller\activity;

use app\common\controller\Api;
use app\common\model\User;
use app\common\model\activity\RedPacket;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\Log;
use think\Loader;

/**
 * 推荐活动接口
 */
class Recommend extends Api
{

    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];

    //推荐活动开始时间
    const  STAR_TIME = 1635997402, END_TIME = 1635997402;


    /**
     * 用户红包列表
     */
    public function index()
    {


        $param = $this->request->param();
        $user = $this->auth->getUser();
        if (!isset($user['id'])) $this->error("获取用户信息失败", '', 2);

        if (isset($param['state']) && $param['state'] == 1){
            $where = ['uid' => $user['id'],'status'=>1];
        }else{
            $where= ['uid' => $user['id']];
        }

        $data = RedPacket::where($where)->field('id,award_amount,status,award_type,mark')->select();
        $this->success('请求成功', $data);

    }


    /**
     * 创建红包奖励
     */


    public function creatRedPacket($param ,$isOrder = null)
    {

       Log::error('执行了钩子事件');
        Log::error('父级id是:'.$param['p_id']);
        Log::error('我的id是:'.$param['id']);
        Log::error('我是额外参数:'.$isOrder);


//        if ($param['createtime'] < self::STAR_TIME){ return false;}
        if (empty($param['p_id'])) { return false;}
        $parn_user = User::get($param['p_id']);
        if ($parn_user['level']>1){return false;}

        $parnData = RedPacket::get(['uid' => $param['p_id']]);

        Db::startTrans();
        try {
            if ($parnData) {
                //奖励已领取金额
                $award_nums = $parnData->where(['award_type' => 1])->sum('award_amount');
                //红包已领取个数
                $award_count = $parnData->where(['uid' => $param['p_id']])->count('id');
                //已领取红包总额
                $data = [];
                $condition = false;
                if ($award_count != 0 && is_int($award_count / 20)) {
                    $is_order = User::where(['p_id' => $param['p_id'], 'isOrder' => 1, 'createtime' => self::STAR_TIME])->count();
                    if ($is_order != 0 && is_int($is_order / 5)) {
                        $parn_user->where(['id' => $param['p_id']])->setInc('receive_num', 1);
                        $condition = true;
                    }
                }

                if ($award_nums < 38 || $award_nums < ((38 * $parn_user->receive_num)) || $condition) {
                    //1为现金红包  2为积分
                    $pr = [1 => 40, 2 => 60];
                    $n = $this->randomSelect($pr);
//        print_r(intval(100/1));
                    if ($n == 1) {
                        //红包抽奖
                        $lists = [
                            '1,10' => 52,
                            '10,20' => 30,
                            '20,30' => 15,
                            '30,38' => 3
                        ];
                        $num = $this->randomSelect($lists, true);
                        $data['award_amount'] = rand($num[0], $num[1]);
                        $data['award_type'] = 1;

                        if ($award_nums + $data['award_amount'] > 38 * $parn_user->receive_num) {
                            $data['award_amount'] = (38 * $parn_user->receive_num) - $award_nums;
                        }

                    } else {
                        //积分抽奖
                        $lists = [
                            '50,100' => 52,
                            '100,150' => 30,
                            '150,200' => 18
                        ];
                        $num = $this->randomSelect($lists, true);
                        $data['award_amount'] = rand($num[0], $num[1]);
                        $data['award_type'] = 2;
                    }

                    if ($condition) {
                        $data['award_type'] = 1;
                        $data['award_amount'] = 2;
                    }
                } else {
                    //积分抽奖
                    $lists = [
                        '50,100' => 52,
                        '100,150' => 35,
                        '150,200' => 15
                    ];
                    $num = $this->randomSelect($lists, true);
                    $data['award_amount'] = rand($num[0], $num[1]);
                    $data['award_type'] = 2;

                }
            } else {

                //1为现金红包  2为积分
                $pr = [1 => 40, 2 => 60];
                $n = $this->randomSelect($pr);
                if ($n == 1) {
                    //红包抽奖
                    $lists = [
                        '1,10' => 52,
                        '10,20' => 30,
                        '20,30' => 15,
                        '30,38' => 3
                    ];
                    $num = $this->randomSelect($lists, true);
                    $data['award_amount'] = rand($num[0], $num[1]);
                    $data['award_type'] = 1;

                } else {
                    //积分抽奖
                    $lists = [
                        '50,100' => 52,
                        '100,150' => 30,
                        '150,200' => 18
                    ];

                    $num = $this->randomSelect($lists, true);
                    $data['award_amount'] = rand($num[0], $num[1]);
                    $data['award_type'] = 2;
                }
            }
            $data['uid'] = $param['p_id'];
            if ($isOrder){
                $data['mark'] = '来自' . $param['real_name'] . '推荐首单奖励';
            }else{
                $data['mark'] = '来自' . $param['real_name'] . '的推荐奖励';
            }
            $data['create_time'] = time();

            RedPacket::insert($data);

            Db::commit();
        } catch (ValidateException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } catch (\PDOException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } catch (\Exception $e) {
            Db::rollback();
            $msg['msg'] = $e->getMessage();
            $msg['info'] = $e->getFile() . ':' . $e->getLine();
            Log::error($msg);
            $this->error($e->getMessage());
        }

    }



    /**
     * 领取红包
     */
    public function receiveRedPack()
    {

        $param = $this->request->param();
        $user = $this->auth->getUser();
        //参数验证
        $validate = Loader::validate('Recommend');
        if(!$validate->check($param)){
            $this->error($validate->getError());
        }
        if (!isset($user['id'])) $this->error("获取用户信息失败", '', 2);
        $where['uid']= $user['id'];
        $where['id'] = $param['id'];
        $where['award_amount'] = $param['awardAmount'];
        $where['award_type'] = $param['awardType'];

        $res = RedPacket::where($where)->find();
        if (!$res) {

            $this->error('该用户暂无红包', '', 2);
        }

        if ($user['level'] != 1) {
            $this->error('未达到领取条件', '', 2);
        }

        $result='';
        Db::startTrans();
        try {
            if ($res['award_type'] == 1 && $res['status'] == 1 ){
                User::bonus($res['award_amount'], $user['id']);
                $this->auth->add_log($user['id'], $user['fictitious_id'], $res['award_amount'], '推荐奖励', '+');
            }else if($res['award_type'] == 2 && $res['status'] == 1 ){
                User::score($res['award_amount'], $user['id'], '推荐奖励');
            }else{
                $this->error('未达到领取条件', '', 2);

            }
            $result = $res->where(['uid' => $user['id'], 'id' => $param["id"]])->update(['status' => 2,'update_time' =>time()]);
            Db::commit();

        } catch (ValidateException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } catch (\PDOException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } catch (\Exception $e) {
            Db::rollback();
            $msg['msg'] = $e->getMessage();
            $msg['info'] = $e->getFile() . ':' . $e->getLine();
            Log::error($msg);
            $this->error('领取失败');
        }

        $res = RedPacket::where($where)->find();

        if ($result) {
            $this->success('领取成功',$res);
        }else{
            $this->error('领取失败');
        }


    }


    /**
     * 概率选择
     *  $array        $data = [
     *            '1,8' => 10,
     *            '8,15' => 20,
     *            '10,20' => 30 ,
     *            '20,30' => 50
     *                ];
     * value为百分比
     *
     *
     *  return string
     */

    public function randomSelect(&$array, $i = false)
    {
        $datas = $array;
        if (!is_array($datas) || count($datas) == 0) {
            return false;
        }
        asort($datas); //按照大小排序
        $random = rand(1, 100);
        $sum = 0;
        $flag = '';
        foreach ($datas as $key => $data) {
            $sum += $data;
            // 看取出来的随机数属于哪个区间
            if ($random <= $sum) {
                $flag = $key;
                break;
            }
        }
        if ($flag == '') {  // 如果传递进来的值的和小于100 ，则取概率最大的。
            $keys = array_keys($datas);
            $flag = $keys[count($keys) - 1];
        }
        if ($i) {
            $flag = explode(",", $flag);

        }

        return $flag;
    }


}