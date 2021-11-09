<?php

namespace app\api\controller;

use addons\epay\library\Service;
use app\admin\controller\agent\Order;
use app\api\controller\Pay;
use app\common\controller\Api;
use app\common\library\Auth;
use \app\common\model\agent\Gift as GiftModel;
use app\common\model\agent\GiftOrder;
use app\common\model\UserAddress;
use Symfony\Component\HttpFoundation\Request;
use think\Db;
use think\Exception;
use think\Log;

/**
 * 代理商礼包接口
 */
class Gift extends Api
{

    protected $noNeedLogin = ['details'];
    protected $noNeedRight = ['*'];

    /*
     * 商品详情
     * */
    public function details(){
        if(!$this->request->param("id"))$this->error("id为空",'',2);
        $list=GiftModel::get($this->request->param("id"));
        unset($list["status_text"]);
        unset($list["weigh"]);
        $this->success('返回成功', $list);
    }
    public function build_order_no(){
        return date('ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
    }
    //创建订单
    public function createOrder(){
        if (!$this->request->param("id")) $this->error("id为空", '', 2);
        if (!$this->request->param("address_id")) $this->error("地址id为空", '', 2);
        if (!$this->request->param("")) $this->error("支付方式为空", '', 2);
        $param=$this->request->param();
        $user=$this->auth->getUser();
        $site = \think\Config::get("site");
        $goods=GiftModel::get($this->request->param("id"));
        if(!$goods) $this->error("商品不存在", '', 2);
        // 判断用户是否实名
        if($user -> real_status == 0) $this->error("用户未实名，即将跳转到实名页面",'',3);
        // 判断用户是否设置支付密码
        if(!$user -> pay_pwd && $param["pay_data"]==1) $this->error("用户未设置支付密码，即将跳转到设置支付密码",'',4);
        if($param["pay_data"]==1 && $user["money"]<$goods["price"]) $this->error("用户余额不足",'',2);
        $address=UserAddress::where(["id"=>$param["address_id"],"user_id"=>$user["id"],"is_del"=>0])->find();
        if(!$address) $this->error("地址不存在", '', 2);
        $order=GiftOrder::where(["status"=>0,"user_id"=>$user["id"],"agent_gift_id"=>$param["id"]])
            ->lock(true)->find();
        if($order){
            $out_trade_no = $this->build_order_no();
            GiftOrder::where(["id" => $order["id"]])->update([
                "out_trade_no" => $out_trade_no,
                "pay_data" => $param["pay_data"],
                "address_id" => $param["address_id"],
                ]);
            if ($order["pay_data"] == 1) {
                //余额支付
                $this->success(1,["id"=>$order["id"],"list"=>[]]);
            }
            if ($order["pay_data"] == 2) {
                //微信支付
                $out_trade_no = Pay::build_order_no();
                $list = Pay::wx_pay("购买代理商礼包", $out_trade_no, $order["all_money"]);
                $this->success("", $list);
            }
            if ($order["pay_data"] == 3) {
                //支付宝支付
                $out_trade_no =$this->build_order_no();
                $list=Service::submitOrder($goods["price"],
                    $out_trade_no, "alipay", ["name"],
                    $this->request->domain() . '/api/gift/alipay_notify/paytype/alipay',
                    $this->request->domain() . '/api/gift/alipay_notify/paytype/alipay',
                    "app");
                $this->success("", $list);
            }
        }
        Db::startTrans();
        try {
            $out_trade_no = $this->build_order_no();
            $data=[
                "agent_gift_id"=>$param["id"],
                "user_id"=>$user["id"],
                "status"=>0,
                "pay_data"=>$param["pay_data"],
                "all_money"=>$goods["price"],
                "actual_money"=>$goods["price"],
                "createtime"=>time(),
                "address_id"=>$param["address_id"],
                "address"=>json_encode($address),
                "goods_details"=>json_encode($goods),
                "out_trade_no"=>$out_trade_no,
            ];
                /*GiftModel::update([
                    "stock"=>$goods["stock"]-$param["num"],//剩余库存减去兑换数量
                    "sales"=>$goods["sales"]+$param["num"],//销量
                ]);*/
            $id=GiftOrder::insertGetId($data);
            $list=[];
            if($param["pay_data"]==3){
                //支付宝支付
                $list=Service::submitOrder($goods["price"],
                    $out_trade_no, "alipay", ["name"],
                    $this->request->domain() . '/api/gift/alipay_notify/paytype/alipay',
                    $this->request->domain() . '/api/gift/alipay_notify/paytype/alipay',
                    "app");
                //$response=Pay::alipay("预售订单", $goods["price"], $out_trade_no, '');
            }
            if($param["pay_data"]==2){
                //微信支付
                $list=Pay::wx_pay("预售订单",$out_trade_no,$goods["price"], '');
            }
            Db::commit();
        }catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success(1,["id"=>$id,"list"=>$list]);
        if($param["pay_data"]==0) $this->success(1,["id"=>$id]);//余额支付
        if($param["pay_data"]==2){
            //支付宝支付
            $response=Pay::alipay("预售订单", $goods["price"], $out_trade_no, '');
            $this->success(1,["id"=>$id,"list"=>$response]);
        }
        if($param["pay_data"]==1){
            //微信支付
            $list=Pay::wx_pay("预售订单",$out_trade_no,$goods["price"], '');
            $this->success(1,["id"=>$id,"list"=>$list]);
        }
    }

    /*支付*/
    public function pay(){
        if (!$this->request->param('id')) $this->error("订单id为空", "", 2);
        $user = $this->auth->getUser();
        $id = $this->request->param('id');
        $site = \think\Config::get("site");
        $order = GiftOrder::get($this->request->param('id'));
        if ((int)$order["status"] != 0) $this->error("订单状态错误", "", 2);
        if($order["pay_data"]==1 && $user["money"]<$order["all_money"]) $this->error("用户余额不足",'',2);
        if ((int)$order["pay_data"] == 1) {
            if(!$this->request->param('pay_pwd')) $this->error("支付密码为空","",2);
            if(md5(md5($this->request->param('pay_pwd')) . $user["pay_salt"])!=$user["pay_pwd"]) $this->error("支付密码错误","",2);
            Db::startTrans();
            try {
                //余额支付
                $order->status="1";
                $order->paytime= time();
                $order->save();
                \app\common\model\User::money(-($order["all_money"]), $user["id"], "购买代理商礼包");
                \app\common\model\User::update(["is_agent"=>1],["id"=>$user["id"]]);
                $this -> auth -> upgrade_agent(1,$order["id"]);//用户升级代理商
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
        }
        $this->success("支付成功");
        if ($order["pay_data"] == 2) {
            //微信支付
            $out_trade_no = Pay::build_order_no();
            GiftOrder::where(["id" => $id])->update(["out_trade_no" => $out_trade_no]);
            $list = Pay::wx_pay("购买代理商礼包", $out_trade_no, $order["all_money"]);
            $this->success("", $list);
        }
        if ($order["pay_data"] == 3) {
            //支付宝支付
            $out_trade_no = Pay::build_order_no();
            GiftOrder::where(["id" => $id])->update(["out_trade_no" => $out_trade_no]);
            $list = Pay::alipay("购买代理商礼包", $out_trade_no, $order["all_money"]);
            $this->success("", $list);
        }
    }
    /*回调*/
    public function giftNot($data){
        //if (!WeChat::checkSign($data)) {
        if (!EasyWeChat::getEncryptionKey($data)) {
            write_log(['msg' => '微信回调签名错误', 'data' => $data], __DIR__);
            return false;
        }
        $order =GiftOrder::where(['out_trade_no'=>$data['out_trade_no']])->find();
        if (!$order || $order['status']) {
            return false;
        }
        //状态:0=待支付,1=已支付,2=已取消,3=待发货,4=已发货,5=确认收货,6=维权,7=待选择,8=已完成
        $order->status = 1;
        $order->paytime = time();
        //$goods->out_trade_no = $data["out_trade_no"];
        $order->save();
        \app\common\model\User::update(["is_agent"=>1],["id"=>$order["user_id"]]);
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
            $order =GiftOrder::where(['out_trade_no'=>$data["out_trade_no"]])->find();
            $order->status = 1;
            $order->paytime = time();
            //$goods->out_trade_no = $data["out_trade_no"];
            $order->save();
            \app\common\model\User::update(["is_agent"=>1],["id"=>$order["user_id"]]);
            $this -> auth -> upgrade_agent(1,$order["id"]);//用户升级代理商
        } catch (Exception $e) {
        }
        die();

        $request = Request::createFromGlobals();
        $data = $request->request->count() > 0 ? $request->request->all() : $request->query->all();
        Log::write($data,'notice');
        try {
            //$payamount = $paytype == 'alipay' ? $data['total_amount'] : $data['total_fee'] / 100;
            $order =GiftOrder::where(['out_trade_no'=>$data["out_trade_no"]])->find();
            $order->status = 1;
            $order->paytime = time();
            //$goods->out_trade_no = $data["out_trade_no"];
            $order->save();
            \app\common\model\User::update(["is_agent"=>1],["id"=>$order["user_id"]]);
        } catch (Exception $e) {
        }
        die();

        //原始订单号
        $out_trade_no = input('out_trade_no');
        //支付宝交易号
        $trade_no = input('trade_no');
        //交易状态
        $trade_status = input('trade_status');
        if ($trade_status == 'TRADE_FINISHED' || $trade_status == 'TRADE_SUCCESS') {
            //支付成功
            //自己的业务代码
            $order =GiftOrder::where(['out_trade_no'=>$trade_no])->find();
            $order->status = 1;
            $order->paytime = time();
            //$goods->out_trade_no = $data["out_trade_no"];
            $order->save();
            \app\common\model\User::update(["is_agent"=>1],["id"=>$order["user_id"]]);
        }else{
            //支付失败
        }
    }
}
