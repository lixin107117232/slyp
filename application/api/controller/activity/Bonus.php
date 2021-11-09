<?php


namespace app\api\controller\activity;

use app\common\controller\Api;
use app\common\model\activity\Bonus as BonusModel;
use app\common\model\User;
use EasyWeChatComposer\Laravel\ServiceProvider;
use think\Db;
use think\Log;


/**
 * 活动奖金
 */

class Bonus  extends Api
{

    const BONUS_ONE = 10,BONUS_TWO = 20,BONUS_THREE = 30;

    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];




    /**
     * 获取用户奖金信息
     */
    public function index(){


        $user = $this->auth->getUser();

        if (!isset($user['id'])) $this->error("获取用户信息错误", '', 2);

        $res = BonusModel::where(['uid'=>$user['id']])->find();

        if (!$res){
            $list['bonus_one']   = 0;
            $list['bonus_two']   = 0;
            $list['bonus_three'] = 0;
            $this->success('请求成功',$list);
        }

        $this->success('请求成功',$res);

    }


    /**
     * 添加用户奖金信息
     */
    public  function createBonusInfo($user = null){

        if (!$user){
            $user = $this->auth->getUser();
        }
        if ($user['level'] != 1 ){return false;}
        if (!isset($user['id'])){return false;};
        $res = BonusModel::where(['uid'=>$user['id']])->find();
        Db::startTrans();
        try {
            if ($res){
                switch ($res['num_tn'])
                {
                    case 1:
                        $data['bonus_two']= 1;
                        break;
                    case 2:
                        $data['bonus_three'] = 1;
                        break;
                    default: return false;
                }
                $data['num_tn'] = $res['num_tn'] + 1 ;
                BonusModel::where(['uid'=>$user['id']])->update($data);
            }else{
                $data['uid'] = $user['id'];
                $data['num_tn'] = 1; //首次下单
                $data['bonus_one'] = 1;
                BonusModel::insert($data);
            }
            Db::commit();
        } catch (ValidateException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } catch (PDOException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
    }


    /**
     * 领取用户奖金
     */
    public function receiveBonus(){


        $param =$this->request->param();
        $user = $this->auth->getUser();
        if (!$this->request->param("reward")) $this->error("参数错误", '', 2);
        if (!isset($user['id'])) $this->error("获取用户信息错误", '', 2);
        $res = BonusModel::where(['uid'=>$user['id']])->find();
        if ($user['level'] != 1 )$this->error("未达到领取条件", '', 2);

        if (!$res) $this->error("未达到领取条件", '', 2);
        $field= '';
        Db::startTrans();
        try {
            switch ($param['reward'])
            {
                case self::BONUS_ONE:
                    if($res['bonus_one'] == 1  && $res['num_tn'] >= 1  ){
                        $field='bonus_one';
                        User::bonus(self::BONUS_ONE,$user['id']);
                        $this->auth->add_log($user['id'],$user['fictitious_id'],self::BONUS_ONE,'活动奖励','+');

                    }else{
                        $this->error("未达到领取条件", '', 2);
                    }
                    break;
                case self::BONUS_TWO:
                    if($res['bonus_two']== 1 && $res['num_tn'] >= 2 ){
                        $field='bonus_two';
                        User::bonus(self::BONUS_TWO,$user['id']);
                        $this->auth->add_log($user['id'],$user['fictitious_id'],self::BONUS_TWO,'活动奖励','+');

                    }else{
                        $this->error("未达到领取条件", '', 2);
                    }
                    break;
                case self::BONUS_THREE:
                    if($res['bonus_three']== 1 && $res['num_tn'] >= 3 ){
                        $field ='bonus_three';
                        User::bonus(self::BONUS_THREE,$user['id']);
                        $this->auth->add_log($user['id'],$user['fictitious_id'],self::BONUS_THREE,'活动奖励','+');
                    }else{
                        $this->error("未达到领取条件", '', 2);
                    }
                    break;
                default:$this->error("参数错误", '', 2);
            }

            BonusModel::where(['uid'=>$user['id']])->update([$field=>2]);
            Db::commit();

            $this->success("领取成功");

        } catch (ValidateException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } catch (PDOException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
    }

}