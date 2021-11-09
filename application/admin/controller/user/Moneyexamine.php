<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use app\common\model\Back;
use app\common\model\Config;
use app\common\model\UserBack;
use app\common\model\UserBonusLog;
use app\common\model\UserMoneyLog;
use app\common\model\UserPaymentMethod;
use think\Db;

/**
 * 余额审核管理
 *
 * @icon fa fa-circle-o
 */
class Moneyexamine extends Backend
{
    
    /**
     * Moneyexamine模型对象
     * @var \app\admin\model\Moneyexamine
     */
    protected $model = null;
    protected $searchFields = 'user.fictitious_id,user.mobile';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Moneyexamine;
        $this->view->assign("statusList", $this->model->getStatusList());

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
                ->with(['user'])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $k => $v) {
                $v->visible(['id','user_id','score','old_score','pay_method','method_image',
                    'method_id','memo','status','createtime','updatetime']);
                $v->visible(['user']);
                $v->getRelation('user')->visible(['username','fictitious_id','mobile','nickname']);


                if($v->pay_method == 1){
                    $v->pay_method = "微信";
                }else if($v->pay_method == 2){
                    $v->pay_method = "支付宝";
                }else if($v->pay_method == 3){
                    $v->pay_method = "银行卡";
                }

                if($v->status == 1){
                    $v->status = "审核中";
                }else if($v->status == 2){
                    $v->status = "审核成功";
                }else if($v->status == 3){
                    $v->status = "审核失败";
                }
            }
            $data = json_decode(json_encode($list->items()),true);

            foreach ($data as $k => $v){
                $data[$k]['zhenshi_name'] = "";
                $data[$k]['zhenshi_phone'] = "";
                $data[$k]['back_name'] = "";
                $data[$k]['back_user_name'] = "";
                $data[$k]['back_no'] = "";
                $data[$k]['back_branch'] = "";
                if($v['pay_method'] == '支付宝'){
                    // 支付宝
                    // 查询支付宝收款方式
                    $row = UserPaymentMethod::where(['id' => $v['method_id'],'user_id' => $v['user_id']])
                        -> find();
                    $data[$k]['zhenshi_name'] = $row['name'] ?? "";
                    $data[$k]['zhenshi_phone'] = $row['phone'] ?? "";
                }
                if($v['pay_method'] == '银行卡'){
                    // 银行卡
                    // 查询银行卡收款方式
                    $row = UserBack::where(['id' =>  $v['method_id']]) -> find();
                    $data[$k]['back_name'] = $row['back_name'];
                    $data[$k]['back_user_name'] = $row['user_name'];
                    $data[$k]['back_no'] = $row['back_no'];
                    $data[$k]['back_branch'] = $row['back_branch'];
                }
            }
            $result = array("total" => $list->total(), "rows" => $data);

            return json($result);
        }
        return $this->view->fetch();
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
                    // 如果状态为 3审核失败，则将余额退回
                    if($params['status'] == 3){
                        \app\admin\model\User::where('id',$params['user_id'])
                            -> setInc('money',$params['old_score']);
                        $user = \app\admin\model\User::where('id',$params['user_id'])
                            -> field('fictitious_id,money')
                            -> find();
                        // 添加记录
                        $data = [
                            'user_id' => $params['user_id'],
                            'money' => "+".$params['old_score'],
                            'before' => $user['money'],
                            'after' => $user['money']+$params['old_score'],
                            'memo' => "审核失败退回",
                            'createtime' => time(),
                        ];
                        UserMoneyLog::insert($data);
                    }
                    if($params['status'] == 2) {
                        Db('config')->where(['id'=>89])->setInc('value',$params['old_score']);
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
        // 查询用户信息
        $user = \app\admin\model\User::where('id',$row['user_id'])
            -> field('fictitious_id,mobile')
            -> find();
        $row = json_decode(json_encode($row),true);
        $row['fictitious_id'] = $user['fictitious_id'];
        $row['mobile'] = $user['mobile'];

        $this->view->assign('status',build_select('row[status]',['','审核中','审核成功','审核失败'],$row['status'],['class' => 'form-control selectpicker']));

        $this->view->assign("row", $row);
        return $this->view->fetch();
    }


    /**
     * 查看收款详情
     *
    */
    public function select_details($ids = ""){
        // 查询商品信息
        $data = $this -> model -> where('id',$ids) -> find();
        $row = [];
        // 提现方式 pay_method == 1 微信
        if($data['pay_method'] == 1){

        }
        // 提现方式 pay_method == 2 支付宝
        if($data['pay_method'] == 2){
            // 查询支付宝收款方式
            $row = UserPaymentMethod::where(['id' => $data['method_id'],'user_id' => $data['user_id']]) -> find();
        }
        // 提现方式 pay_method == 3 银行卡
        if($data['pay_method'] == 3){
            $row = UserBack::where(['id' =>  $data['method_id']]) -> find();
        }
        $user = \app\admin\model\User::where('id',$data['user_id']) -> find();
        $this->view->assign("row", $row);
        $this->view->assign("pay_method",$data['pay_method']);
        $this->view->assign("fictitious_id",$user['fictitious_id']);
        return $this->view->fetch();
    }


}
