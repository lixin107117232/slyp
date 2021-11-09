<?php

namespace app\admin\controller\user;

use app\admin\controller\hospital\Order;
use app\admin\model\UserAchievement;
use app\admin\model\UserToken;
use app\api\controller\Token;
use app\common\controller\Backend;
use app\common\library\Auth;
use app\common\model\Back;
use app\common\model\integral\Exchange;
use app\common\model\UserAddress;
use app\common\model\UserBack;
use app\common\model\UserBonusLog;
use app\common\model\UserMoneyLog;
use app\common\model\UserPaymentMethod;
use app\common\model\UserScoreLog;
use app\common\model\WithdrawalLog;
use fast\Random;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 会员管理
 *
 * @icon fa fa-user
 */
class User extends Backend
{

    protected $relationSearch = true;
    protected $searchFields = 'fictitious_id,username,nickname,mobile';

    /**
     * @var \app\admin\model\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('User');
    }

    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $list = $this->model
                ->with('group')
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $k => $v) {
                $v->avatar = $v->avatar ? cdnurl($v->avatar, true) : letter_avatar($v->nickname);
                if($v->level == 2){
                    $v->level = "代理商";
                }else if($v->level == 3){
                    $v->level = "总代理";
                }else{
                    $v->level = "消费者";
                }
                if($v -> real_status == 1){
                    $v->real_status = "已实名";
                }else{
                    $v->real_status = "未实名";
                }
                $v->hidden(['password', 'salt']);
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }

        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        return parent::add();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        $this->modelValidate = true;
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    // 判断是否修改支付密码
                    if($params['pay_pwd'] != ''){
                        $salt = Random::alnum();
                        $params['pay_pwd'] = md5(md5($params['pay_pwd']) . $salt);
                        $params['pay_salt'] = $salt;
                    }else{
                        unset($params['pay_pwd']);
                    }
                    $result = $row->allowField(true)->save($params);
                    Db::commit();
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
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
//        $this->view->assign('groupList', build_select('row[group_id]', \app\admin\model\UserGroup::column('id,name'), $row['group_id'], ['class' => 'form-control selectpicker']));
        $this->view->assign('real_status_list',build_select('row[real_status]',['未实名','已实名'],$row['real_status'],['class' => 'form-control selectpicker']));
        $this->view->assign('level',build_select('row[level]',['','消费者','代理商','总代理'],$row['level'],['class' => 'form-control selectpicker']));
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
        // 开启事务
        Db::startTrans();
        try {

            $ids = $ids ? $ids : $this->request->post("ids");
            $row = $this->model->get($ids);
            $this->modelValidate = true;
            if (!$row) {
                $this->error(__('No Results were found'));
            }

            // 删除用户业绩明细中的内容
            UserAchievement::where('user_id',$ids) -> delete();
            // 删除用户收获地址
            UserAddress::where('user_id',$ids) -> delete();
            // 删除用户银行卡信息
            UserBack::where('user_id',$ids) -> delete();
            // 删除用户奖金变动表信息
            UserBonusLog::where('user_id',$ids) -> delete();
            // 删除用户余额审核表内容
            \app\admin\model\Moneyexamine::where('user_id',$ids) -> delete();
            // 删除用户余额变动表信息
            UserMoneyLog::where('user_id',$ids) -> delete();
            // 删除用户收款方式表
            UserPaymentMethod::where('user_id',$ids) -> delete();
            // 删除用户积分变动表
            UserScoreLog::where('user_id',$ids) -> delete();
            // 删除用户token表信息
            UserToken::where('user_id',$ids) -> delete();
            // 删除用户提现记录表
            WithdrawalLog::where('user_id',$ids) -> delete();

            // 删除医美订单表中的内容
            \app\admin\model\Order::where('user_id',$ids) -> delete();
            // 删除抢购订单表中内容
            \app\common\model\seckill\Order::where('user_id',$ids) -> delete();
            // 删除兑换订单表中的内容
            Exchange::where('user_id',$ids) -> delete();

            Auth::instance()->delete($row['id']);
            Db::commit();
            $this->success();
        } catch (Exception $e) {
            Db::rollback();
            $this->setError($e->getMessage());
            return false;
        }

    }

}
