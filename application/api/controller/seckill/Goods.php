<?php



namespace app\api\controller\seckill;



use addons\epay\library\Service;

use app\api\controller\Pay;

use app\common\controller\Api;

use app\common\model\Config as ConfigModel;

use app\common\model\seckill\SeckillAttrKey;

use app\common\model\seckill\SeckillAttrVal;

use app\common\model\seckill\SeckillSku;

use app\common\model\seckill\Goods as GoodsModel;

use app\common\model\seckill\Specs;

use app\common\model\User;

use app\common\model\UserAddress;

use think\Db;

use think\Exception;
use think\Log;
use think\Hook;



/**

 * 秒杀商品接口

 */

class Goods extends Api

{

    protected $noNeedLogin = ['index','index1','index2',"details","goodsSpecs",'getSpecs','snapup','purchase'];

    protected $noNeedRight = ['*'];

    /**

     * 秒杀列表

     */

    public function index()

    {


//        if (!$this->request->param("play")) $this->error("场次为空", '', 2);

        $page = !$this->request->param('page')?1:$this->request->param('page');//页

        $field="id,status,price,specs_data,stock,sales,weigh,createtime";

//        if($this->request->param("play")==2){
//            //第一场
//            $field="id,status,price,specs_data,two_stock stock,sales,weigh,createtime";
//        }

        $list=GoodsModel::field($field)

            ->with(["allgoods"=>function($query){

            $query->withField('id,name,cover_image');//->order("weigh desc,createtime desc");

        }])->where(["status"=>0])

            ->order("weigh desc,createtime desc")

            ->paginate(10,'',['page' => $page, 'list_rows' => 10]);

        $this->success('返回成功', $list);

    }



    /**

     * 秒杀列表

     */

    public function index1()

    {

        if (!$this->request->param("play")) $this->error("场次为空", '', 2);

        $page = !$this->request->param('page')?1:$this->request->param('page');//页

        $field="id,status,price,specs_data,stock,sales,weigh,createtime";

//        if($this->request->param("play")==2){

            //第二场

//            $field="id,status,price,specs_data,two_stock stock,sales,weigh,createtime";

//        }

        $list=GoodsModel::field($field)

            ->with(["allgoods"=>function($query){

            $query->withField('id,name,cover_image');//->order("weigh desc,createtime desc");

        }])->where(["status"=>0])

            ->order("weigh desc,createtime desc")

            ->paginate(8,'',['page' => $page, 'list_rows' => 8]);

        $this->success('返回成功', $list);

    }

    /**

     * 秒杀列表

     */

    public function index2()

    {


        $page = !$this->request->param('page')?8:$this->request->param('page');//页

        $field="id,status,price,specs_data,stock,sales,weigh,createtime,is_act";

//        if($this->request->param("play")==2){
//
//            //第一场
//
//            $field="id,status,price,specs_data,two_stock stock,sales,weigh,createtime,is_act";
//        }

        $list=GoodsModel::field($field)
    
            ->with(["allgoods"=>function($query){

                $query->withField('id,name,cover_image');//->order("weigh desc,createtime desc");

            }])->where(["status"=>0,"is_act"=>1])

            ->order("weigh desc,createtime desc")

            ->paginate($page,'',['page' => 1,'list_rows' => $page]);

        $this->success('返回成功', $list);

    }

    /*

    * 商品详情

    * */

    public function details(){

        if(!$this->request->param("id"))$this->error("id为空",'',2);

        $list=GoodsModel::field("id,status,price,specs_data,stock,sales,weigh,createtime")

            ->with(["allgoods"=>function($query){

            $query->withField('id,name,cover_image,content,video,images')->order("weigh desc,createtime desc");

        }])->where(["goods.id"=>$this->request->param("id")])

            ->find();

        $list["createtime"]=date("Y-m-d H:i:s",$list["createtime"]);

        $this->success('返回成功', $list);

    }

    /*

     * 商品规格

     * */

    public function goodsSpecs()

    {

        if (!$this->request->param("id")) $this->error("id为空", '', 2);

        $goods =GoodsModel::field("specs_data,stock,sales,surplus_stock")

            ->where(["id" => $this->request->param("id")])

            ->find();

        if ($goods["specs_data"] == 1)

        {

            //有规格

            $data=SeckillAttrKey::with(['Seckillattrval'])->where(['item_id'=>$this->request->param("id")])->select();

            /*$need=[];

            foreach ($data as $item) {

                $need[]=$item->toArray();

            }

            $sku=SeckillSku::where(['item_id'=>$this->request->param("id")])

                ->where("stock>0 or two_stock>0")

                ->select();

            $skus=[];

            foreach ($sku as $item) {

                $skus[]=$item->toarray();

            }*/

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

        if (!$this->request->param("play")) $this->error("场次为空", '', 2);

        $field="sku_id,stock,sales";

//        if($this->request->param("play")==2){
//
//            //第一场
//
//            $field="sku_id,two_stock stock,two_sales sales";
//
//        }

        $param=$this->request->param();

        $goods=GoodsModel::get($param["id"]);

        if(empty($goods)) $this->error("商品不存在", '', 2);

        $sku=SeckillSku::where('attr_symbol_path' , $param["path"])-> find();

        if(!$sku)$this -> error("当前属性值不存在",'',2);

        // 获取商品信息

        $sku_data = SeckillSku::where(['item_id'=>$param["id"],'attr_symbol_path' => $param["path"]])

            -> field($field)

            -> find();

        if(!empty($sku_data)){

            $this->success('请求成功',$sku_data);

        }else{

            $this->success('未查询到数据',$sku_data);

        }

    }

    /*

     * 创建订单

     *

     * */

    public function createOrder(){

        if (!$this->request->param("id")) $this->error("id为空", '', 2);

        if (!$this->request->param("address_id")) $this->error("地址id为空", '', 2);

        if (!$this->request->param("play")) $this->error("场次为空", '', 2);

        if (!$this->request->param("pay_data")) $this->error("支付方式为空", '', 2);

        $param=$this->request->param();


        $site = \think\Config::get("site");

        $user=$this->auth->getUser();

        // 判断用户是否实名

        if($user -> real_status == 0) $this->error("用户未实名，即将跳转到实名页面",'',3);

        // 判断用户是否设置支付密码

        if(!$user -> pay_pwd && $param["pay_data"]==1) $this->error("用户未设置支付密码，即将跳转到设置支付密码",'',4);

        if((int)$param["pay_data"]==2) $this->error('微信支付开发中');

        $study=\app\common\model\seckill\Order::where(["user_id"=>$user["id"],"status"=>0])->find();

        if($study) $this->error("您有待付款订单，请付款后再购买",'',2);

        $goods=GoodsModel::with(["allgoods"=>function($query){

            $query->withField('id,name,cover_image,content,video');

        }])->where(["goods.id"=>$this->request->param("id"),"status"=>0])

            ->lock(true)

            ->find();

        if($param["pay_data"]==1 && $user["money"]<$goods["price"]) $this->error("用户余额不足",'',2);


        if(!$goods) $this->error("商品不存在", '', 2);

        $time = strtotime(date('H:i:s',time()));

        $seckill['one_seckill_end_time']= strtotime($site["one_seckill_start_time"])+ ($site["seckill_time"]*60);

        if ($seckill['one_seckill_end_time'] < $time && $time >= strtotime($site["two_seckill_start_time"])  ){

            $param["play"] = 2;
        }

        if($user["isOrder"]==1 && $param["play"]==1 && time()<strtotime($site["one_seckill_start_time"]) ){

            $this->error("未到抢购时间", '', 2);

        }

        if($user["isOrder"]==1 && $param["play"]==1 && strtotime($site["one_seckill_start_time"].":00")+$site["seckill_time"]*60<strtotime(date('H:i:s',time()))){

            $this->error("抢购活动已结束", '', 2);

        }

        //第二场抢购

        if($user["isOrder"]==1 && $param["play"]==2 && !time()>strtotime($site["two_seckill_start_time"]) && (float)date('H:i',time())<=24)

        {

            $this->error("未到抢购时间", '', 2);

        }

        if($user["isOrder"]==1 && $param["play"]==2 && strtotime($site["two_seckill_start_time"].":00")+$site["seckill_time"]*60<strtotime(date('H:i:s',time()))){

            $this->error("抢购活动已结束", '', 2);

        }

        $address=UserAddress::where(["id"=>$param["address_id"],"user_id"=>$user["id"],"is_del"=>0])->find();

        if(!$address) $this->error("地址不存在", '', 2);

        if((int)$goods['specs_data']==1){

            /*选用规格*/

            if(!$this->request->param("specs_id")) $this->error("规格id为空", '', 2);

            $specs=SeckillSku::where(["sku_id"=>$param["specs_id"],"item_id"=>$this->request->param("id")])

                ->lock(true)

                ->find();

            if(!$specs) $this->error("该规格不存在", '', 2);

            if($specs["stock"]<=0 ) $this->error("该商品库存不足", '', 2);

//            if($specs["two_stock"]<=0 && $user["isOrder"]==1 && $param["play"]==2) $this->error("该商品库存不足", '', 2);

        }

        if($goods["stock"]<=0 ) $this->error("该商品库存不足", '', 2);

//        if($goods["two_stock"]<=0 && $user["isOrder"]==1&& $param["play"]==2) $this->error("该商品库存不足", '', 2);

        Db::startTrans();

        try {

            include_once CONF_PATH . 'api' . DS . 'Pay.php';

            $pay=new Pay();

            $out_trade_no=$pay->build_order_no();

            $order_no=$pay->build_order_no();

            $data=[

                "seckill_goods_id"=>$param["id"],

                "user_id"=>$user["id"],

                "status"=>0,

                "pay_data"=>$param["pay_data"],

                "all_money"=>$goods["price"],

                "actual_money"=>$goods["price"],

                "createtime"=>time(),

                "address_id"=>$param["address_id"],

                "address"=>$address,

                "goods_details"=>json_encode($goods),

                "time_expire"=>strtotime("+".$site["create_seckill_time"]." minute",time()),//交易结束时间

                "out_trade_no"=>$out_trade_no,

                "play"=>$param["play"],
                "order_no"=>"sk".$order_no,

            ];
            if($this->request->param("specs_id")){
                //有规格



                    $data["specs_id"]=$param["specs_id"];

                    $sku=SeckillAttrVal::field("GROUP_CONCAT(attr_value) attr_value")

                        ->where("symbol","in",$specs["attr_symbol_path"])->find();

                    $data["specs_name"]=$sku["attr_value"];
                    SeckillSku::where(["sku_id"=>$param["specs_id"]])->setDec('stock',1);
                    SeckillSku::where(["sku_id"=>$param["specs_id"]])->setInc('sales',1);
            }
            GoodsModel::where(["id"=>$param["id"]])->setDec('stock',1);
            GoodsModel::where(["id"=>$param["id"]])->setInc('sales',1);

           /* if ($goods["stock"]>=0 || $user["isOrder"]!=1){

                $good_update=[

                    "stock"=>$goods["stock"]-1,//库存减1

                    "sales"=>$goods["sales"]+1,//销量

                ];

                if($param["play"]==2){

                    $good_update=[

                        "two_stock"=>$goods["two_stock"]-1,//库存减1

                        "two_sales"=>$goods["two_sales"]+1,//销量

                    ];

                }

                GoodsModel::update($good_update,["id"=>$param["id"]]);

            }*/

            $id=\app\common\model\seckill\Order::insertGetId($data);

            $list=[];

            if($param["pay_data"]==3){

                //支付宝支付

               /* $list=Pay::alipay("抢购商品", $goods["price"], $out_trade_no, '');*/

                /*$pay=Pay::alipay(Service::getConfig("alipay"));

                $orderdata=[

                    "out_trade_no"=>$out_trade_no,

                    "body"=>$goods["allgoods"]["name"],

                    "total_fee"=>$goods["price"]*100,

                    "openid"=>$user["openid"],

                ];

                $response=$pay->alipay($orderdata);*/
               $timeExpire = date('Y-m-d H:i:s',$data['time_expire']);

                $list=Service::submitOrder($goods["price"],

                    $out_trade_no, "alipay", $goods["allgoods"]["name"],

                    $this->request->domain() . '/api/seckill.order/alipay_notify/paytype/alipay',

                    $this->request->domain() . '/api/seckill.order/alipay_notify/paytype/alipay',

                    "app",'',$timeExpire);

            }

            if($param["pay_data"]==2){

                //微信支付

                /*$pay=new Pay();

                $list=$pay->wx_pay("抢购商品",$out_trade_no,$goods["price"], '');*/

                $list=Service::submitOrder($goods["price"]*100, $out_trade_no, "wechat", $goods["allgoods"]["name"],

                    $this->request->domain() . '/api/seckill.goods/order/assNot/paytype/wechat',

                    $this->request->domain() . '/api/seckill.goods/order/returnx/paytype/wechat', "app");

            }

            Db::commit();

        }catch (Exception $e) {

            Db::rollback();

            $this->error($e->getMessage());

        }

        $this->success(1,["id"=>$id,"list"=>$list]);





    }



    /*

     * 抢购时间

     * */

    public function snapup(){

        $site = \think\Config::get("site");

        $this->success(1,[
            "one_seckill_start_time"=>strlen($site["one_seckill_start_time"])>=2?$site["one_seckill_start_time"]:"0".$site["one_seckill_start_time"],
            "two_seckill_start_time"=>strlen($site["two_seckill_start_time"])>=2?$site["two_seckill_start_time"]:"0".$site["two_seckill_start_time"],
            "one_seckill_end_time"=> date('H:i',strtotime($site["one_seckill_start_time"])+ ($site["seckill_time"]*60)),
            "two_seckill_end_time"=> date('H:i',strtotime($site["two_seckill_start_time"])+ ($site["seckill_time"]*60)),
        ]);

    }

    /*

        * 预购  获取选择商品信息

        * */

    public function purchase(){

        if (!$this->request->param("id")) $this->error("id为空", '', 2);

        $user=$this->auth->getUser();

        $goods=GoodsModel::with(["allgoods"=>function($query){

            $query->withField('id,name,cover_image');

        }])->where(["goods.id"=>$this->request->param("id")])

            ->find();

        if(empty($goods)) $this->error("商品不存在", '', 2);

        //if((int)$goods["specs_data"]==0 && $goods["stock"]<=0 && $user["isOrder"]==1) $this->error("商品库存不足", '', 2);

        $data=[

            "id"=>$goods["id"],

            "name"=>$goods["allgoods"]["name"],

            "cover_image"=>$goods["allgoods"]["cover_image"],

            "price"=>$goods["price"],

            "all_money"=>(int)$goods["price"]*1,

        ];

        if($goods["specs_data"]==1){

            if (!$this->request->param("sku_id")) $this->error("sku_id为空", '', 2);

            $sku=SeckillSku::get($this->request->param("sku_id"));

            if($sku["stock"]<=0) $this->error("商品库存不足", '', 2);

            $val=SeckillAttrVal::field("GROUP_CONCAT(attr_value) attr_value")

                ->where("symbol","in",$sku["attr_symbol_path"])->find();

            if(empty($sku)) $this->error("规格不存在", '', 2);

            $data["sku_id"]=$this->request->param("sku_id");

            $data["sku_name"]=$val["attr_value"];

        }

        $data["number"]=1;

        $this->success("返回成功",$data);



    }

}

