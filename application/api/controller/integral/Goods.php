<?php

namespace app\api\controller\integral;

use addons\epay\library\Service;
use app\api\controller\Pay;
use app\common\controller\Api;
use app\common\model\hospital\Sku;
use app\common\model\integral\Exchange;
use app\common\model\integral\Goods as GoodsModel;
use app\common\model\integral\IntegralAttrKey;
use app\common\model\integral\IntegralAttrVal;
use app\common\model\integral\IntegralSku;
use app\common\model\Specs;
use app\common\model\User;
use app\common\model\integral\Type;
use app\common\model\UserAddress;
use Monolog\Handler\IFTTTHandler;
use think\Db;
use think\Exception;
use think\Log;

/**
 * 积分商城商品接口
 */
class Goods extends Api
{

    protected $noNeedLogin = ['index', 'index1','details','goodsSpecs','getSpecs','purchase','getGoodsList'];
    protected $noNeedRight = ['*'];
    /**
     * 获取积分商城商品
     */
    public function index()
    {
        $page = !$this->request->param('page')?1:$this->request->param('page');//页
        $type_id=$this->request->param('type_id');
        $where="1=1";
        if(isset($type_id))
        {
            $where=["type_id"=>$type_id];
        }
        $list=GoodsModel::with(["allgoods"=>function($query){
            $query->withField('id,name,cover_image');//->order("weigh desc,createtime desc")
        }])->where($where)
            ->where(["status"=>0])
            ->order("weigh desc,createtime desc")
            ->paginate(10,'',['page' => $page, 'list_rows' => 10]);
        $this->success('返回成功', $list);
    }


    /**
     * 获取积分商城商品
     */
    public function index1()
    {
        $page = !$this->request->param('page')?1:$this->request->param('page');//页
        $where="1=1";

        $list=GoodsModel::with(["allgoods"=>function($query){
            $query->withField('id,name,cover_image');//->order("weigh desc,createtime desc")
        }])->where($where)
            ->where(["status"=>0,'is_act'=>1])
            ->order("weigh desc,createtime desc")
            ->paginate($page,'',['page' => 1, 'list_rows' => $page]);
        $this->success('返回成功', $list);
    }

    /**
     * 获取积分商城商品
     * 搜索列表
     */

    public function getGoodsList()
    {
        $page = !$this->request->param('page')?1:$this->request->param('page');//页
        $type_id=$this->request->param('typeId');
        $param['order'] =$this->request->param('order');
        $param['price'] =$this->request->param('price');

        if(isset($param['order']))
        {
            $order= $param['order'];
        }else if(isset($param['price'])){
            $order= $param['price'];
        }else{
            $order="weigh desc";
        }
        if(isset($type_id))
        {
            $list=GoodsModel::with(["allgoods"=>function($query){
                $query->withField('id,name,cover_image');//->order("weigh desc,createtime desc")
            }])->where("FIND_IN_SET(:value, `type_id`)", ['value'=>$type_id])
                ->where(["status"=>0])
                ->order($order)
                ->paginate(8,'',['page' => $page, 'list_rows' => 8]);
        }else{
            $list=GoodsModel::with(["allgoods"=>function($query){
                $query->withField('id,name,cover_image');//->order("weigh desc,createtime desc")
            }])->where('1=1')
                ->where(["status"=>0])
                ->order($order)
                ->paginate(8,'',['page' => $page, 'list_rows' => 8]);
        }


        $this->success('返回成功', $list);
    }

    /*
     * 商品详情
     * */
    public function details(){
        if(!$this->request->param("id"))$this->error("id为空",'',2);
        $list=GoodsModel::with(["allgoods"=>function($query){
            $query->withField('id,name,cover_image,content,video,images')->order("weigh desc,createtime desc");
        }])->where(["goods.id"=>$this->request->param("id")])
            ->find();
        $this->success('返回成功', $list);
    }
    /*
     * 商品规格
     * */
    public function goodsSpecs()
    {
        if (!$this->request->param("id")) $this->error("id为空", '', 2);
        $goods = GoodsModel::field("specs_data,stock,sales,surplus_stock")
            ->where(["id" => $this->request->param("id")])
            ->find();
        if ($goods["specs_data"] == 1)
        {
            //有规格
            $data=IntegralAttrKey::with(['Integralattrval'])
                ->where(['item_id'=>$this->request->param("id")])
                ->order("attr_key_id")
                ->select();
            $need=[];
            foreach ($data as $item) {
                $need[]=$item->toArray();
            }
            $this->success("返回成功",$data);
        }
        $this->success("未查询到数据",[]);
    }
    /*
     * 获取规格
     * */
    public function getSpecs()
    {
        if (!$this->request->param("id")) $this->error("商品id为空", '', 2);
        if (!$this->request->param("path")) $this->error("规格为空", '', 2);
        $param=$this->request->param();
        $goods=GoodsModel::get($param["id"]);
        if(empty($goods)) $this->error("商品不存在", '', 2);
        $sku=IntegralSku::where('attr_symbol_path' , $param["path"])-> find();
        if(!$sku)$this -> error("当前属性值不存在",'',2);
        // 获取商品信息
        $sku_data = IntegralSku::where(['item_id'=>$param["id"],'attr_symbol_path' => $param["path"]])
            -> field('sku_id,stock,sales')
            -> find();
        if(!empty($sku_data)){
            $this->success('请求成功',$sku_data);
        }else{
            $this->success('未查询到数据',$sku_data);
        }
    }
    //生成唯一订单号
    public function build_order_no(){
        return date('ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
    }
   /*
    * 预购  获取选择商品信息
    * */
    public function purchase(){
        if (!$this->request->param("id")) $this->error("id为空", '', 2);
        if (!$this->request->param("number")) $this->error("数量为空", '', 2);
        $goods=GoodsModel::with(["allgoods"=>function($query){
            $query->withField('id,name,cover_image,content,video')->order("weigh desc,createtime desc");
        }])->where(["goods.id"=>$this->request->param("id")])
            ->find();
        if(empty($goods)) $this->error("商品不存在", '', 2);
        if($goods["specs_data"]==0 && $goods["stock"]<$this->request->param("number")) $this->error("商品库存不足", '', 2);
        $data=[
            "id"=>$goods["id"],
            "name"=>$goods["allgoods"]["name"],
            "cover_image"=>$goods["allgoods"]["cover_image"],
            "number"=>(int)$this->request->param("number"),
            "num"=>$goods["num"],
            "all_money"=>(int)$goods["num"]*(int)$this->request->param("number"),
        ];
        if($goods["specs_data"]==1){
            if (!$this->request->param("sku_id")) $this->error("sku_id为空", '', 2);
            $sku=IntegralSku::get($this->request->param("sku_id"));
            if($sku["stock"]<=0) $this->error("商品库存不足", '', 2);
            $val=IntegralAttrVal::field("GROUP_CONCAT(attr_value) attr_value")
                ->where("symbol","in",$sku["attr_symbol_path"])->find();
            if(empty($sku)) $this->error("规格不存在", '', 2);
            $data["sku_id"]=$this->request->param("sku_id");
            $data["sku_name"]=$val["attr_value"];
        }
        $this->success("返回成功",$data);

    }

    /*
     * 兑换
     * */
    public function exchange(){
        if (!$this->request->param("id")) $this->error("id为空", '', 2);
        if (!$this->request->param("num")) $this->error("数量为空", '', 2);
        if (!$this->request->param("address_id")) $this->error("地址id为空", '', 2);
        $param=$this->request->param();
        $site = \think\Config::get("site");
        $user=$this->auth->getUser();
        $user=User::get($user["id"]);
        // 判断用户是否实名
        if($user -> real_status == 0) $this->error("用户未实名，即将跳转到实名页面",'',3);
        // 判断用户是否设置支付密码
        if(!$user -> pay_pwd && ($param["pay_data"]==0 || $param["pay_data"]==1)) $this->error("用户未设置支付密码，即将跳转到设置支付密码",'',4);

        $goods=GoodsModel::with(["allgoods"=>function($query){
            $query->withField('id,name,cover_image,content,video')->order("weigh desc,createtime desc");
        }])->where(["goods.id"=>$this->request->param("id")])
            ->lock(true)
            ->find();
        /*if((int)$param["pay_data"]==2 && (int)$site["wx_pay"]==1 && $goods["exchange_data"]!='1') $this->error("微信支付未开启",'',2);
        if((int)$param["pay_data"]==3 && (int)$site["ali_pay"]!=1 && $goods["exchange_data"]!='1') $this->error("支付宝支付未开启",'',2);
       */ if(!$goods) $this->error("商品不存在", '', 2);
        if((int)$goods['specs_data']==1){
            if(!$this->request->param("specs_id")) $this->error("规格id为空", '', 2);
            $specs=IntegralSku::where(["sku_id"=>$param["specs_id"],
                "item_id"=>$this->request->param("id")])
                ->where("stock>0")
                ->lock(true)
                ->find();
            if(!$specs) $this->error("该规格已售空或该规格不存在", '', 2);
            if($specs["stock"]<=0) $this->error("该商品库存不足", '', 2);
        }else{
            if($goods["stock"]<=0 || $goods["stock"]<$param["num"]) $this->error("该商品已兑完或库存不足", '', 2);
        }
        $address=UserAddress::where(["id"=>$param["address_id"],"user_id"=>$user["id"],"is_del"=>0])->find();
        if(!$address) $this->error("地址不存在", '', 2);
        $money=$goods["num"]*$param["num"];
        if($goods["exchange_data"]!=0 && $param["pay_data"]==0) $this->error("该商品未开启积分兑换", '', 2);
        if($user["money"]<$money && (int)$param["pay_data"]==1) $this->error('余额不足', '', 2);
        if($user["score"]<$money && (int)$param["pay_data"]==0) $this->error('积分不足', '', 2);
        include_once CONF_PATH . 'api' . DS . 'Pay.php';
        Db::startTrans();
        try {
            $pay=new Pay();
            $out_trade_no=$pay->build_order_no();
            $data=[
                "goods_id"=>$goods["id"],
                "user_id"=>$user["id"],
                "status"=>0,
                "mode"=>0,
                "num"=>$param["num"],
                "address_id"=>$param["address_id"],
                "address"=>$address,
                "all_money"=>$money,
                "actual_money"=>$money,
                "goods_details"=>json_encode($goods),
                "time_expire"=>strtotime("+".$site["create_cancel_time"]." minute",time()),//交易结束时间
                "out_trade_no"=>$out_trade_no,
                "createtime"=>time(),
            ];
            if($this->request->param("specs_id")){
                $data["specs_id"]=$this->request->param("specs_id");
                $sku=IntegralSku::get($this->request->param("specs_id"));
                $attr_symbol_path=explode(",",$sku["attr_symbol_path"]);
                $sku_name=IntegralAttrVal::field("GROUP_CONCAT(attr_value) attr_value")
                    ->whereIn("symbol",$attr_symbol_path)->find();
                $data["specs_name"]=$sku_name["attr_value"];
                IntegralSku::update([
                    "stock"=>$sku["stock"]-$param["num"],//剩余库存减去兑换数量
                    "sales"=>$sku["sales"]+$param["num"],//销量
                ],["sku_id"=>$param["specs_id"]]);
            }
            /*减去商品库存*/
            GoodsModel::update([
                    "stock"=>$goods["stock"]-$param["num"],//剩余库存减去兑换数量
                    "sales"=>$goods["sales"]+$param["num"],//销量
            ],["id"=>$param["id"]]);
            $id=Exchange::insertGetId($data);
            $list=[];
            if($param["pay_data"]==3){
                /*$response=Pay::alipay("抢购商品", $goods["price"], $out_trade_no, 'https://yimei.sctqmt.com/api/integral.Exchange/integralNot');
                $this->success(1,["id"=>$id,"list"=>$response]);*/
                $list=Service::submitOrder(0.01,
                    $out_trade_no, "alipay", $goods["allgoods"]["name"],
                    $this->request->domain() . '/api/integral.Exchange/integralNot/paytype/alipay',
                    $this->request->domain() . '/api/integral.Exchange/integralNot/paytype/alipay',
                    "app");
            }
            if($param["pay_data"]==2){
                $list=Service::submitOrder($goods["price"]*100, $out_trade_no,
                    "wechat", $goods["allgoods"]["name"],
                    $this->request->domain() . '/api/integral.Exchange/integralNot/paytype/wechat',
                    $this->request->domain() . '/api/integral.Exchange/integralNot/paytype/wechat',
                    "app");
                //$list=Pay::wx_pay("抢购商品",$out_trade_no,$goods["price"], 'https://yimei.sctqmt.com/api/integral.Exchange/integralAlipayNot');
            }
            Db::commit();
        }catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success(1,["id"=>$id,"list"=>$list]);
        die();
        if($goods['specs_data']==0)
        {
            //没有规格
            if($param["pay_data"]==0)
            {
                if($goods["exchange_data"]!=0) $this->error("该商品未开启积分兑换", '', 2);
                //积分兑换
                Db::startTrans();
                //没有规格
                try {
                    $id=Exchange::create([
                        "goods_id"=>$goods["id"],
                        "user_id"=>$user["id"],
                        "status"=>3,
                        "mode"=>0,
                        "num"=>$param["num"],
                        "address_id"=>$param["address_id"],
                        "address"=>$address,
                        "all_money"=>$money,
                        "actual_money"=>$money,
                        "goods_details"=>json_encode($goods),
                        "out_trade_no"=>$this->build_order_no(),
                    ]);
                    $this->success(1,["id"=>$id]);
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
            }
            if($goods["exchange_data"]!=1) $this->error("该商品未开启余额支付", '', 2);
            //余额或者微信支付宝支付
            Db::startTrans();
            //支付流程
            try {
                $out_trade_no=$this->build_order_no();
                Exchange::create([
                    "goods_id"=>$goods["id"],
                    "user_id"=>$user["id"],
                    "status"=>0,
                    "mode"=>0,
                    "num"=>$param["num"],
                    "all_money"=>$money,//总金额
                    "actual_money"=>$money,//实际支付金额
                    "address_id"=>$param["address_id"],
                    "address"=>$address,
                    "goods_details"=>json_encode($goods),
                    "out_trade_no"=>$out_trade_no,
                ]);
                Db::commit();
                if($param["pay_data"]==0) $this->success(1,["id"=>$id]);
                if($param["pay_data"]==3){
                    $response=Pay::alipay("积分兑换", $goods["price"], $out_trade_no, '');
                    $this->success(1,["id"=>$id,"list"=>$response]);
                }
                if($param["pay_data"]==2){
                    $list=Pay::wx_pay("积分兑换",$out_trade_no,$money,"");
                    $this->success(1,["id"=>$id,"list"=>$list]);
                }
                $this->success(1,["id"=>$id,"list"=>$list]);
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }

        }
        die();
        if($goods["exchange_data"]==0)
        {
           /* if(!$this->request->param('pay_pwd')) $this->error("支付密码为空","",2);
            $user=User::get($user["id"]);
            if(md5(md5($this->request->param('pay_pwd')) . $user["pay_salt"])!=$user["pay_pwd"]) $this->error("支付密码错误","",2);
           */
            //积分兑换
            if($goods['specs_data']==0)
            {
                Db::startTrans();
                //没有规格
                try {
                    $id=Exchange::create([
                        "goods_id"=>$goods["id"],
                        "user_id"=>$user["id"],
                        "status"=>3,
                        "mode"=>0,
                        "num"=>$param["num"],
                        "address_id"=>$param["address_id"],
                        "address"=>$address,
                        "all_money"=>$money,
                        "actual_money"=>$money,
                        "goods_details"=>json_encode($goods),
                        "out_trade_no"=>$this->build_order_no(),
                    ]);
                  /*  GoodsModel::where(["id"=>$goods["id"]])->update([
                        "sales"=>$goods["sales"]+$param["num"],
                        "surplus_stock"=>$goods["surplus_stock"]-$param["num"],
                    ]);
                    User::score(-($money),$user["id"],"兑换商品");*/
                    $this->success(1,["id"=>$id]);
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
            }else
            {
                //选了规格
                try {

                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                    return json_encode(0);
                }
            }
            $this->success();
        }
        if($goods["exchange_data"]==1)
        {
            //支付流程
            try {
                $out_trade_no=$this->build_order_no();
                Exchange::create([
                    "goods_id"=>$goods["id"],
                    "user_id"=>$user["id"],
                    "status"=>0,
                    "mode"=>0,
                    "num"=>$param["num"],
                    "all_money"=>$money,//总金额
                    "actual_money"=>$money,//实际支付金额
                    "address_id"=>$param["address_id"],
                    "address"=>$address,
                    "goods_details"=>json_encode($goods),
                    "out_trade_no"=>$out_trade_no,
                ]);
                Db::commit();
                if($param["pay_data"]==0) $this->success(1,["id"=>$id]);
                if($param["pay_data"]==3){
                    $response=Pay::alipay("积分兑换", $goods["price"], $out_trade_no, '');
                    $this->success(1,["id"=>$id,"list"=>$response]);
                }
                if($param["pay_data"]==2){
                    $list=Pay::wx_pay("积分兑换",$out_trade_no,$money,"");
                    $this->success(1,["id"=>$id,"list"=>$list]);
                }
                $this->success(1,["id"=>$id,"list"=>$list]);
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
        }
    }

}
