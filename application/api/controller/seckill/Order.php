<?php

namespace app\api\controller\seckill;

use addons\epay\library\Service;
use app\api\controller\Pay;
use app\api\controller\WxPay;
use app\common\controller\Api;
use app\common\library\Auth;
use app\common\model\Config;
use app\common\model\seckill\Order as OrderModel;
use  \app\common\model\seckill\Goods as GoodsModel;
use app\common\model\seckill\Preorder;
use app\common\model\seckill\SeckillSku;
use app\common\model\User;
use EasyWeChatComposer\EasyWeChat;
use Symfony\Component\HttpFoundation\Request;
use think\Db;
use think\Exception;
use think\Log;
use think\Hook;


/**
 * 秒杀订单接口
 */
class Order extends Api
{

    //赠送积分比例
    const RATIO = 0.3;
    protected $noNeedLogin = ['alipay_notify','seckillNot','details','index',];
    protected $noNeedRight = ['*'];

    /**
     * 所有商品列表
     */
    public function index()
    {
        $user=$this->auth->getUser();
        //=User::get(2);
        $page = !$this->request->param('page')?1:$this->request->param('page');//页
        $where="1=1";
        $status=$this->request->param('status');
        if((isset($status) || $status==0 )&& $status!='') $where=["status"=>$this->request->param('status')];
        $list = OrderModel::field("id,seckill_goods_id,status,status_remark,pay_data,all_money,actual_money,num,
        createtime,goods_details,out_trade_no,time_expire,specs_name")
            ->where(["user_id"=>$user["id"]])
            ->where($where)
            ->order("createtime desc,paytime desc")
            ->paginate(10,'',['page' => $page, 'list_rows' => 10])
            ->each(function ($v){
                if((int)$v["status"]==0 && $v["time_expire"]<time()){
                    $order=OrderModel::get($v["id"]);
                    if($order){
                        Db::startTrans();
                        try {
                            $order->status = 2;
                            $order->status_remark = "用户超时未付款";
                            $order->cancel_time =time();
                            $order->save();
                            if ($order['play'] == 1  ){
                                if ($order["specs_id"]){
                                    SeckillSku::where(["sku_id" => $order["specs_id"]])
                                        ->setInc("stock", 1);//加回库存
                                }
                                GoodsModel::where(["id" => $order["seckill_goods_id"]])
                                    ->setInc("stock", 1);//加回库存
                            }else{
                                if ($order["specs_id"]){
                                    SeckillSku::where(["sku_id" => $order["specs_id"]])
                                        ->setInc("two_stock", 1);//加回库存
                                }
                                GoodsModel::where(["id" => $order["seckill_goods_id"]])
                                    ->setInc("two_stock", 1);//加回库存
                            }
                            Db::commit();
                        } catch (Exception $e) {
                            Db::rollback();
                            $this->error($e->getMessage());
                        }
                    }
                    $v["over_status"]=1;
                }
                $v["time_expire"]=date("Y-m-d H:i:s",$v["time_expire"]);
                $v["createtime"]=date("Y-m-d H:i:s",$v["createtime"]);
                $details=json_decode($v["goods_details"],true);
                $dat=[
                    "id"=>$details["id"],
                    "name"=>$details["allgoods"]["name"],
                    "cover_image"=>$details["allgoods"]["cover_image"],
                ];
                $v["goods_details"]=$dat;
                return $v;
            });
        $this->success('返回成功', $list);
    }

    /*
        * 取消订单
        * */
    public function cancel()
    {
        if (!$this->request->param('id')) $this->error("订单id为空", "", 2);
        $order = OrderModel::get($this->request->param('id'));
        //状态:0=待支付,1=已支付,2=已取消,3=待发货,4=已发货,5=确认收货,6=维权
        if ($order["status"] != 0) $this->error("订单状态错误", "", 2);
        /*if (!$this->request->param('status_remark')) $this->error("取消理由为空", "", 2);
        OrderModel::update([
            "status" => 2,
            "status_remark" => $this->request->param('status_remark'),
            "cancel_time"=>time()
        ], ["id" => $order["id"]]);*/
        Db::startTrans();
        try {
           /* $order->status = 2;
            $order->status_remark = "用户取消订单";
            $order->cancel_time =time();
            $order->save();*/
            OrderModel::update([
                "status"=>'2',
                "status_remark"=>"用户取消订单",
                "cancel_time"=>time(),
            ],["id"=>$order["id"]]);
            if(!empty($order["specs_id"]) && $order["play"]==1) SeckillSku::where(["sku_id"=>$order["specs_id"]])->setInc("stock",1); //第一场抢购多规格
            if(!empty($order["specs_id"]) && $order["play"]==2) SeckillSku::where(["sku_id"=>$order["specs_id"]])->setInc("two_stock",1);//第二场抢购多规格
            if($order["play"]==1) GoodsModel::where(["id"=>$order["seckill_goods_id"]])->setInc("stock",1);//第一场抢购;
            if($order["play"]==2) GoodsModel::where(["id"=>$order["seckill_goods_id"]])->setInc("two_stock",1);//第二场抢购;
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('取消成功');
    }

    /*
     * 确认收货
     * */
    public function receipt()
    {
        if (!$this->request->param('id')) $this->error("订单id为空", "", 2);
        $order = OrderModel::get($this->request->param('id'));
        //状态:0=待支付,1=已支付,2=已取消,3=待发货,4=已发货,5=确认收货,6=维权
        if ((int)$order["status"] != 4) $this->error("订单状态错误", "", 2);
        $order->status="5";
        $order->rgoodstime=time();
        $order->save();
        $this->success('确认收货成功');
    }

    /*
     * 订单详情
     * */
    public function details()
    {
        if (!$this->request->param('id')) $this->error("订单id为空", "", 2);
        $list = OrderModel::get(["id" => $this->request->param('id')]);
        $list["goods_details"] = json_decode($list["goods_details"], true);
        $list["goods_details"] = $list["goods_details"]["allgoods"];
        $list["address"] = json_decode($list["address"], true);
        if((int)$list["status"]==0 && $list["time_expire"]<time()){
            $list["over_status"]=1;
            Db::startTrans();
            try {
                /*$list->status="2";
                $list->status_remark="用户超时未付款";
                $list->cancel_time=time();
                $list->save();*/
                OrderModel::update([
                    "status"=>'2',
                    "status_remark"=>"用户超时未付款",
                    "cancel_time"=>time(),
                ],["id"=>$list["id"]]);
                if ($list["specs_id"]) {
                    if(SeckillSku::get($list["specs_id"])){
                        SeckillSku::where(["sku_id" => $list["specs_id"]])->setInc("stock", 1);//加回库存
                    }
                }
                GoodsModel::where(["id" => $list["seckill_goods_id"]])->setInc("stock", 1);//加回库存
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
        }
        if($list["time_expire"]) $list["time_expire"]=date("Y-m-d H:i:s",$list["time_expire"]);
        if($list["createtime"]) $list["createtime"]=date("Y-m-d H:i:s",$list["createtime"]);
        $s=is_numeric($list["paytime"]) ? date("Y-m-d H:i:s", $list["paytime"]) : $list["paytime"];
        if($list["paytime"]) $list["paytime_text"]=$s;
        $this->success('返回成功', $list);
    }

    /*
     * 选择要商品还是积分
     * ischoice 选择:0=商品,1=积分
     * 状态:0=待支付,1=已支付,2=已取消,3=待发货,4=已发货,5=确认收货,6=维权,7=待选择,8=已完成
     * */
    public function choice()
    {
        if (!$this->request->param('id')) $this->error("订单id为空", "", 2);
        if (!$this->request->param('ischoice') && $this->request->param('ischoice')!=0) $this->error("选择为空", "", 2);
        $user = $this->auth->getUser();
        $order = OrderModel::get(["id" => $this->request->param('id')]);
        if ($order["status"] != 7) $this->error("订单状态不对", '', 2);
        if ($this->request->param('ischoice') == 0) {
            $order->status="3";
            $order->ischoice=$this->request->param('ischoice');
            $order->save();
            //余额支付的用户额外赠送购买金额的30%积分
            if ($order['pay_data'] == 1){
                User::score(ceil($order["all_money"] * self::RATIO) , $user["id"], "抢购订单额外赠送积分");
            }
            /*OrderModel::where(["id" => $this->request->param('ischoice'), "user_id" => $user["id"]])
                ->update(["ischoice" => $this->request->param('ischoice'), "status" => 3]);*/
        } else {
            Db::startTrans();
            try {
                $order->status="8";
                $order->ischoice=$this->request->param('ischoice');
                $order->save();
                /*OrderModel::where(["id" => $this->request->param('id'), "user_id" => $user["id"]])
                    ->update(["ischoice" => $this->request->param('ischoice'), "status" => 8]);*/
                User::score($order["all_money"], $user["id"], "抢购订单选择积分");
                if ($order['pay_data'] == 1){
                    User::score(ceil($order["all_money"] * self::RATIO), $user["id"], "抢购订单额外赠送积分");
                }
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
        }
        $this->success('选择成功');
    }

    public function build_order_no(){
        return date('ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
    }
    /*
     * 支付
     * */
    public function pay(){
        if(!$this->request->param('id')) $this->error("订单id为空","",2);
        $user=$this->auth->getUser();
//        $user["id"]=2;
        $user=User::get($user["id"]);
        /*$user["id"]=1;
        $user["isOrder"]=0;*/
        $id=$this->request->param('id');
        //$pay_pwd = md5(md5($this->request->param('pay_pwd')) . $user["pay_salt"]);
        $order=OrderModel::get($this->request->param('id'));

        $site = \think\Config::get("site");
        if((int)$order["status"]!=0) $this->error("订单状态错误","",2);
        if((int)$user["isOrder"]==1 && $order["play"]==1 && time()<strtotime($site["one_seckill_start_time"])) $this->error("未到抢购时间", '', 2);
        //第二场抢购
        if((int)$user["isOrder"]==1 && $order["play"]==2 && time()<strtotime($site["two_seckill_start_time"])) $this->error("未到抢购时间", '', 2);
        /*if($order["pay_data"]==2 && $site["wx_pay"]=='1') $this->error("微信支付未开启",'',2);
        if($order["pay_data"]==3 && $site["ali_pay"]!='1') $this->error("支付宝支付未开启",'',2);*/
        if((int)$order["pay_data"]==1 && $user["money"]<$order["all_money"]) $this->error("用户余额不足",'',2);
        // 微信支付
        if((int)$order["pay_data"]==2) $this->error('微信支付开发中');
        if($order["time_expire"]<time()){
            //状态:0=待支付,1=已支付,2=已取消,3=待发货,4=已发货,5=确认收货,6=维权,7=待选择,8=已完成
            Db::startTrans();
            try {
                $order->status=2;
                $order->status_remark="用户超时未付款";
                $order->save();
                if ($order["specs_id"]) {
                    SeckillSku::where(["id" => $order["specs_id"]])->setInc("stock", 1);//加回库存
                }
                GoodsModel::where(["id" => $order["seckill_goods_id"]])->setInc("stock", 1);//加回库存
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->error("订单已超时", "", 2);
        }

        $details=json_decode($order["goods_details"],true);

        if(empty($order["specs_id"]))
        {
            if((int)$order["pay_data"]==1){
                /*余额购买*/
                if((int)$order["pay_data"]==1 && !$this->request->param('pay_pwd')) $this->error("支付密码为空","",2);
                if(md5(md5($this->request->param('pay_pwd')) . $user["pay_salt"])!=$user["pay_pwd"]) $this->error("支付密码错误","",2);
                if($user["money"]<$order["all_money"]) $this->error("余额不足",'',2);

                Db::startTrans();
                try {
                    $this->auth->user_write_off($user["id"],$order["all_money"]);
                    OrderModel::where(["id"=>$id])
                        ->update([
                            "status"=>"7",
                            "paytime"=>time(),
                        ]);
                    User::money(-($order["all_money"]),$user["id"],"购买商品");

                    User::update(["isOrder"=>1],["id"=>$user["id"]]);
                    $pre_order=$this->preOrder($id);
                    $this -> auth -> upgrade_agent(2,$order["id"]);//用户升级代理商
                    //$this -> auth -> seckill_add_achievement($user["id"],$order["all_money"]);//用户购买抢购订单，添加业绩明细
                    if($pre_order){
                        Db::commit();
                    }else{
                        $this->success("失败");
                        Db::rollback();
                    }
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if($pre_order){
                    //增加推荐首单奖励事件
                    if ($user['isOrder'] === 0 && $user['level'] == 1 ){
                        $isOrder = 1;
                        Hook::exec('app\\api\\controller\\activity\\Recommend','creatRedPacket',$user ,$isOrder);
                    }
                    //下单有礼
                    if ( $user['level'] == 1 ){
                        Hook::exec('app\\api\\controller\\activity\\Bonus','createBonusInfo',$user);
                    }

                    $this->success("购买成功");
                }else{
                    $this->success("失败");
                }
            }
            if((int)$order["pay_data"]==2)
            {
                //微信支付
                $out_trade_no=$this->build_order_no();
                GoodsModel::where(["id"=>$id])->update(["out_trade_no"=>$out_trade_no]);
                $list=Pay::wx_pay("积分兑换",$out_trade_no,$order["all_money"]);
                $this->success("",$list);
            }
            if((int)$order["pay_data"]==3)
            {
                //支付宝支付
                $out_trade_no=$this->build_order_no();
                $order->out_trade_no=$out_trade_no;
                $order->save();
                $list=Service::submitOrder($order["all_money"],
                    $out_trade_no, "alipay", $details["allgoods"]["name"],
                    $this->request->domain() . '/api/seckill.order/alipay_notify/paytype/alipay',
                    $this->request->domain() . '/api/seckill.order/alipay_notify/paytype/alipay',
                    "app");
                $this->success(1,["id"=>$id,"list"=>$list]);
                //$this->success("",$list);
            }
        }
        if((int)$order["pay_data"]==1){
            /*余额购买*/
            if(!$this->request->param('pay_pwd')) $this->error("支付密码为空","",2);
            if(md5(md5($this->request->param('pay_pwd')) . $user["pay_salt"])!=$user["pay_pwd"]) $this->error("支付密码错误","",2);

            Db::startTrans();
            try {
                $this->auth->user_write_off($user["id"],$order["all_money"]);
                OrderModel::where(["id"=>$id])
                    ->update([
                        "status"=>"7",
                        "paytime"=>time(),
                    ]);
                User::money(-($order["all_money"]),$user["id"],"购买商品");
                User::update(["isOrder"=>1],["id"=>$user["id"]]);
                $pre_order=$this->preOrder($id);
                $this -> auth -> upgrade_agent(2,$order["id"]);//用户升级代理商
                //$this -> auth -> seckill_add_achievement($user["id"],$order["all_money"]);//用户购买抢购订单，添加业绩明细
                if($pre_order){
                    Db::commit();
                }else{
                    $this->success("失败");
                    Db::rollback();
                }
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if($pre_order){
                //增加推荐首单奖励事件
                if ($user['isOrder'] === 0 && $user['level'] == 1 ){
                    $isOrder = 1;
                    Hook::exec('app\\api\\controller\\activity\\Recommend','creatRedPacket',$user ,$isOrder);
                }
                //下单有礼
                if ( $user['level'] == 1 ){
                    Hook::exec('app\\api\\controller\\activity\\Bonus','createBonusInfo',$user);
                }
                $this->success("购买成功");
            }else{
                $this->success("失败");
            }
        }


        if((int)$order["pay_data"]==2)
        {
            //微信
            $out_trade_no=$this->build_order_no();
            GoodsModel::where(["id"=>$id])->update(["out_trade_no"=>$out_trade_no]);
            $list=Pay::wx_pay("整点抢购",$out_trade_no,$order["all_money"],'');
            $this->success("",$list);
        }
        if((int)$order["pay_data"]==3)
        {
            //支付宝支付
            $out_trade_no=$this->build_order_no();
            $order->out_trade_no=$out_trade_no;
            $order->save();
            $list= Service::submitOrder($order["all_money"],
                $out_trade_no,
                "alipay",
                $details["allgoods"]["name"],
                $this->request->domain() . '/api/seckill.order/alipay_notify/paytype/alipay',
                $this->request->domain() . '/api/seckill.order/alipay_notify/paytype/alipay',
                'app');

            //$list=Pay::alipay("整点抢购",$out_trade_no,$order["all_money"],'');
            $this->success(1,["id"=>$id,"list"=>$list]);
            $this->success("",$list);
        }
    }
    /*
     * 创建预售订单
     * */
    protected function preOrder($order_id){
        $order=OrderModel::get($order_id);
        $set = \think\Config::get("site");

        if($order["status"]==7){
            //订单是已支付状态
            $data=[
                [
                    "seckill_goods_id"=>$order["seckill_goods_id"],
                    "price"=>json_decode($order["goods_details"])->one_specs_data,
                    "user_id"=>$order["user_id"],
                    "specs_id"=>$order["specs_id"],
                    "specs_name"=>$order["specs_name"],
                    "order_details"=>json_encode($order),
                    "goods_details"=>$order["goods_details"],
                    "createtime"=>time(),
                    "start_time"=>strtotime('+'.$set["one_seckill_days"].' days', $order["paytime"]),//预售开始时间
                    "end_time"=>strtotime('+'.($set["one_seckill_days"]+$set["days_seckill_cancel"]).' days', $order["paytime"]),//预售结束时间
                    "time_expire"=>strtotime('+'.($set["one_seckill_days"]+$set["days_seckill_cancel"]).' days', $order["paytime"]),//预售结束时间
                ],
                [
                    "seckill_goods_id"=>$order["seckill_goods_id"],
                    "price"=>json_decode($order["goods_details"])->two_specs_data,
                    "user_id"=>$order["user_id"],
                    "specs_id"=>$order["specs_id"],
                    "specs_name"=>$order["specs_name"],
                    "order_details"=>json_encode($order),
                    "goods_details"=>$order["goods_details"],
                    "createtime"=>time(),
                    "start_time"=>strtotime('+'.$set["two_seckill_days"].' days', $order["paytime"]),//预售开始时间
                    "end_time"=>strtotime('+'.($set["two_seckill_days"]+$set["days_seckill_cancel"]).' days', $order["paytime"]),//预售结束时间
                    "time_expire"=>strtotime('+'.($set["two_seckill_days"]+$set["days_seckill_cancel"]).' days', $order["paytime"]),//预售结束时间
                ]
            ];
            Db::startTrans();
            try {
                Preorder::insertAll($data);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                return false;
                $this->error($e->getMessage());
            }
            return true;
        }


        return false;
    }

    /*回调*/
    public function seckillNot(){

        $paytype = $this->request->param('paytype');
        $pay = Service::checkNotify($paytype);
        if (!$pay) {
            echo '签名错误';
            return;
        }
        $data = $pay->verify();
        try {
            $payamount = $paytype == 'wechat' ? $data['total_amount'] : $data['total_fee'] / 100;
            $out_trade_no = $data['out_trade_no'];
            $order =OrderModel::where(['out_trade_no'=>$out_trade_no])->find();
            $order->status = 7;
            $order->paytime = time();
            //$goods->out_trade_no = $data["out_trade_no"];
            $order->save();
            $this->preOrder($order["id"]);
            User::update(["isOrder"=>1],["id"=>$order["user_id"]]);
            //你可以在此编写订单逻辑
        } catch (Exception $e) {
        }
        echo $pay->success(); die();

        //if (!WeChat::checkSign($data)) {
        if (!EasyWeChat::getEncryptionKey($data)) {
            write_log(['msg' => '微信回调签名错误', 'data' => $data], __DIR__);
            return false;
        }
        $order =OrderModel::where(['out_trade_no'=>$data['out_trade_no']])->find();
        if (!$order || $order['status']) {
            return false;
        }
        //状态:0=待支付,1=已支付,2=已取消,3=待发货,4=已发货,5=确认收货,6=维权,7=待选择,8=已完成
        $order->status = 3;
        $order->paytime = time();
        //$goods->out_trade_no = $data["out_trade_no"];
        $order->save();
        $this->preOrder($order["id"]);
        User::update(["isOrder"=>1],["id"=>$order["user_id"]]);
        echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';

    }

    /**
     *支付宝回调
     */
    public function alipay_notify()
    {
        $request = Request::createFromGlobals();
        $data = $request->request->count() > 0 ? $request->request->all() : $request->query->all();
        Log::write($data,'notice');
        try {
            if (isset($data['trade_status']) && $data['trade_status'] == 'TRADE_SUCCESS') {
                //$payamount = $paytype == 'alipay' ? $data['total_amount'] : $data['total_fee'] / 100;
                $out_trade_no = $data['out_trade_no'];

                $order = OrderModel::where(['out_trade_no' => $out_trade_no, "status" => 0])->find();
                $this->auth->user_write_off($order["user_id"], $order["all_money"]);
                $order->status = "7";//状态:0=待支付,1=已支付,2=已取消,3=待发货,4=已发货,5=确认收货,6=维权,7=待选择,8=已完成
                $order->paytime = time();
                //$goods->out_trade_no = $data["out_trade_no"];
                $order->save();
                $this->preOrder($order["id"]);
                $user = User::get($order["user_id"]);
                if ($user['level'] == 1) {
                    Hook::exec('app\\api\\controller\\activity\\Bonus','createBonusInfo',$user);

                }
                //增加推荐首单奖励事件
                if ($user['isOrder'] ===0 && $user['level'] == 1 ){
                    $isOrder = 1;
                    Hook::exec('app\\api\\controller\\activity\\Recommend','creatRedPacket',$user ,$isOrder);
                }
                User::update(["isOrder" => 1], ["id" => $order["user_id"]]);
                $this->auth->upgrade_agent(2, $order["id"]);//用户升级代理商
            }
            //$this -> auth -> seckill_add_achievement($order["iuuser_id"],$order["all_money"]);//用户购买抢购订单，添加业绩明细
        } catch (Exception $e) {
            // halt($e ->getMessage());
        }
        die();


        $paytype = $this->request->param('paytype');
        Log::write($paytype,'notice');
        $pay = Service::checkNotify($paytype);
        Log::write($pay,'error');
        if (!$pay) {
            echo '签名错误';
            trace("签名错误","error");
            return;
        }
        $data = $pay->verify();
        Log::write($data,'notice');
        try {
            $payamount = $paytype == 'alipay' ? $data['total_amount'] : $data['total_fee'] / 100;
            $out_trade_no = $data['out_trade_no'];
            $order =OrderModel::where(['out_trade_no'=>$out_trade_no,"status"=>0])->find();
            $order->status = "7";//状态:0=待支付,1=已支付,2=已取消,3=待发货,4=已发货,5=确认收货,6=维权,7=待选择,8=已完成
            $order->paytime = time();
            //$goods->out_trade_no = $data["out_trade_no"];
            $order->save();
            $this->preOrder($order["id"]);
            User::update(["isOrder"=>1],["id"=>$order["user_id"]]);
            //你可以在此编写订单逻辑
        } catch (Exception $e) {
            trace($e->getMessage(),"error");
            return false;
        }
        echo $pay->success();
        //原始订单号
        $out_trade_no = input('out_trade_no');
        //支付宝交易号
        $trade_no = input('trade_no');
        //交易状态
        $trade_status = input('trade_status');
        if ($trade_status == 'TRADE_FINISHED' || $trade_status == 'TRADE_SUCCESS') {
            //支付成功
            //自己的业务代码
            $order =OrderModel::where(['out_trade_no'=>$trade_no])->find();
            $order->status = 3;
            $order->paytime = time();
            //$goods->out_trade_no = $data["out_trade_no"];
            $order->save();
            $this->preOrder($order["id"]);
            User::update(["isOrder"=>1],["id"=>$order["user_id"]]);
        }else{
            //支付失败
        }
    }
}
