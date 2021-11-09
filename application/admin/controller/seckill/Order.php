<?php

namespace app\admin\controller\seckill;

use app\common\controller\Backend;
use app\common\model\ExpressCompany;
use app\common\model\User;

/**
 * 秒杀板块订单管理
 *
 * @icon fa fa-circle-o
 */
class Order extends Backend
{
    
    /**
     * Order模型对象
     * @var \app\common\model\seckill\Order
     */
    protected $model = null;
    protected $relationSearch = true;
    protected $searchFields = 'user.fictitious_id,user.nickname,user.mobile,out_trade_no';
    //protected $searchFields = "user.fictitious_id,";

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\common\model\seckill\Order;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("ischoiceList", $this->model->getIschoiceList());
        $this->view->assign("payDataList", $this->model->getPayDataList());
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
                    ->with(["user"])
                    ->where($where)
                    ->order($sort, $order)
                    ->paginate($limit);

            foreach ($list as $row) {
                $row->visible(['id','seckill_goods_id','user_id','status','status_remark','ischoice',
                    'pay_data','all_money','actual_money','createtime','paytime','companytime','company_id',
                    'company_name','company_code','numbers','goods_details','out_trade_no','address','specs_name']);
                $row->visible(['user']);
                $row->getRelation('user')->visible(['username','nickname','mobile','avatar','fictitious_id']);
                $details=json_decode($row["goods_details"],true);
                $row["goods_details"]=$details["allgoods"];
                $details=json_decode($row["address"],true);
                $details["address"]=$details["province_city_area"].$details["address"];
                $row["address"]=$details;
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }
    /*
     * 详情
     * */
    public function see($ids=null){
        $list=$this->model->with(['user'=>function($query){
            $query->withField('id,username,nickname,mobile,avatar,fictitious_id');
        }])->where(["order.id"=>$ids])->find();
        $list["goods_details"]=json_decode($list["goods_details"],true);
        $list["address"]=json_decode($list["address"],true);
        $this->assign("row",$list->toArray());
        return $this->view->fetch();
    }
    /*发货*/
    public function degoods($ids=null){
        if ($this->request->isAjax()) {
            $site = \think\Config::get("site");
            $params = $this->request->post("row/a");
            $company=ExpressCompany::get(["id"=>$params["company_id"]]);
            //状态:0=待支付,1=已支付,2=已取消,3=待发货,4=已发货,5=确认收货,6=维权
            $this->model->update([
                "status"=>4,
                "company_id"=>$params["company_id"],
                "company_name"=>$company["name"],
                "company_code"=>$company["code"],
                "numbers"=>$params["numbers"],
                "companytime"=>time(),
                "auto_time"=>strtotime("+".$site["create_rgoods_time"]." day", time())
            ],["id"=>(int)$this->request->param("id")]);
            return $this->success();
        }
        $this->assign("id",$this->request->param("id"));
        return $this->view->fetch("integral/exchange/degoods");
    }


}
