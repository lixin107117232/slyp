<?php

namespace app\admin\controller\seckill;

use app\common\controller\Backend;
use think\Db;
use think\Exception;

/**
 * 预售订单管理
 *
 * @icon fa fa-circle-o
 */
class Preorder extends Backend
{
    
    /**
     * Preorder模型对象
     * @var \app\common\model\seckill\Preorder
     */
    protected $model = null;
    protected $relationSearch = true;
    protected $searchFields = 'user.fictitious_id,user.nickname,user.mobile,out_trade_no';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\common\model\seckill\Preorder;
        $this->view->assign("orderStatusList", $this->model->getOrderStatusList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("payDataList", $this->model->getPayDataList());
    }

    public function see($ids=null)
    {
        $list=$this->model->with(['user'=>function($query){
            $query->withField('id,username,nickname,mobile,avatar,fictitious_id');
        }])->where(["preorder.id"=>$ids])->find();
        if($list["status"]==3 && $list["start_time"]<=time() && time()<=$list["end_time"]) $list["status"]=4;
        $list["goods_details"]=json_decode($list["goods_details"],true);
        $this->assign("row",$list->toArray());
        return $this->view->fetch();
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
            $op=$this->request->get('op');
            $filter=$this->request->get('filter');
            $filter=json_decode($filter);
            $filter= (array)($filter);
            $op=json_decode($op);
            $op= (array)($op);
            $where1="1=1";
            if($filter){
                if(isset($filter["status"])){
                    if($filter["status"]==4){
                        unset($filter['status']);
                        unset($op['status']);
                        $this->request->get(['filter'=>json_encode($filter)]);
                        $this->request->get(['filter'=>json_encode($op)]);
                        $where1=" (preorder.status ='3' or preorder.status ='0') and ".time().">= start_time and end_time >=".time();
                        //$this->request->get(["filter"=>"start_time<=".time()." and end_time<=".time()]);
                    }
                }

            }

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            /*if($where["status"]==4){
                unset($where["status"]);
                $where1=time()." between start_time and end_time";
            }*/
            $list = $this->model
                    ->with(["user"])
                    ->where($where)
                    ->where($where1)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                $row->visible(['id','price','order_status','status','pay_data','actual_money','createtime','paytime','out_trade_no','start_time','end_time']);
                $row->visible(['user']);
                $row->getRelation('user')->visible(['username','nickname','mobile','avatar','fictitious_id']);
                if(((int)$row["status"]==3 || (int)$row["status"]==0) && $row["start_time"]<=time() && time()<=$row["end_time"]) $row["status"]=4;
                if((int)$row["status"]==3 || (int)$row["status"]==0){
                    if($row["end_time"]<time()){
                        $row["pre_status"]=3;
                        $order=\app\common\model\seckill\Preorder::get($row["id"]);
                        if($order){
                            Db::startTrans();
                            try {
                                $order->status = 2;
                                $order->status_remark = "用户超时未付款";
                                $order->cancel_time =time();
                                $order->save();
                                Db::commit();
                            } catch (Exception $e) {
                                Db::rollback();
                                $this->error($e->getMessage());
                            }
                        }
                    }
                }
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

}
