<?php

namespace app\admin\controller\integral;

use app\common\controller\Backend;
use app\common\model\ExpressCompany;

/**
 * 积分商城兑换管理
 *
 * @icon fa fa-circle-o
 */
class Exchange extends Backend
{
    
    /**
     * Exchange模型对象
     * @var \app\common\model\integral\Exchange
     */
    protected $model = null;
    protected $relationSearch = true;
    protected $searchFields = 'user.fictitious_id,user.nickname,user.mobile,out_trade_no';
    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\common\model\integral\Exchange;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("modeList", $this->model->getModeList());
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

            foreach ($list as $row) {
                $row->visible(['id','status','status_remark','mode','all_money','actual_money','num',
                    'createtime','updatetieme','address','goods_details','specs_name','out_trade_no']);
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

    public function see($ids=null){
        $list=$this->model->with(['user'=>function($query){
            $query->withField('id,username,nickname,mobile,avatar,fictitious_id');
        }])->where(["exchange.id"=>$ids])->find();
        $list["goods_details"]=json_decode($list["goods_details"],true);
        $list["address"]=json_decode($list["address"],true);
        $this->assign("row",$list->toArray());
        return $this->view->fetch();
    }
    public function degoods(){
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
        return $this->view->fetch();
    }
}
