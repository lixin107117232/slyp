<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;
use app\common\model\UserBonusLog;
use app\common\model\UserMoneyLog;
use fast\Random;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 会员提现记录管理
 *
 * @icon fa fa-circle-o
 */
class BonusExamine extends Backend
{
    
    /**
     * BonusExamine模型对象
     * @var \app\admin\model\BonusExamine
     */
    protected $model = null;
    protected $searchFields = 'user.fictitious_id,user.mobile';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\BonusExamine;
        $this->view->assign("statusList", $this->model->getStatusList());

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
                ->with(['user'])
                ->where($where)
                ->where("type",'=','3')
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $k => $v) {
                $v->visible(['id','user_id','score','before','after','memo','type','status','createtime']);
                $v->visible(['user']);
                $v->getRelation('user')->visible(['username','fictitious_id','mobile','nickname']);

                $v -> type = "奖金";
                if($v->status == 1){
                    $v->status = "审核中";
                }else if($v->status == 2){
                    $v->status = "审核成功";
                }else if($v->status == 3){
                    $v->status = "审核失败";
                }
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

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
                    // 审核成功之后将资金转入用户账户
                    if($params['status'] == 2){
                        $user_data = \app\admin\model\User::where('id',$params['user_id'])
                            -> field("money,bonus")
                            -> find();
                        // 扣除手续费
                        // 手续费%
                        $shouxu = \app\common\model\Config::get(['id' => 33]) -> value;
                        // 扣除手续费之后的金额（向下取整） 1000    2%      980
                        $num_s = floor($params['score'] - ($params['score']*$shouxu/100));
                        $new_bonus = $user_data['money'] + $num_s;
                        $data = [
                            'money' => $new_bonus
                        ];
                        \app\admin\model\User::where('id',$params['user_id']) -> update($data);
                        // 添加余额详情
                        $data = [
                            'user_id' => $params['user_id'],
                            'money' => "+".$num_s,
                            'before' => $user_data['money'],
                            'after' => $new_bonus,
                            'memo' => "奖金转入",
                            'createtime' => time()
                        ];
                        //写入日志
                        UserMoneyLog::insert($data);
                    }
                    // 如果状态为 3审核失败，则将奖金退回
                    if($params['status'] == 3){
                        \app\admin\model\User::where('id',$params['user_id'])
                            -> setInc('bonus',$params['score']);
                        $user = \app\admin\model\User::where('id',$params['user_id'])
                            -> field('fictitious_id,bonus')
                            -> find();
                        // 添加记录
                        $data = [
                            'user_id' => $params['user_id'],
                            'other_fictitious_id' => $user['fictitious_id'],
                            'bonus' => "+".$params['score'],
                            'before' => $user['bonus'],
                            'after' => $user['bonus']+$params['score'],
                            'memo' => "审核失败退回",
                            'createtime' => time(),
                        ];
                        UserBonusLog::insert($data);
                    }

                    unset($params['user_id']);
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
        $this->view->assign('status',build_select('row[status]',['待审核','审核中','审核成功','审核失败'],$row['status'],['class' => 'form-control selectpicker']));
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }
    

}
