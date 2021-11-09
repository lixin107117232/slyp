<?php

namespace app\api\controller\integral;

use addons\epay\library\Service;
use app\api\controller\Pay;
use app\api\controller\WxPay;
use app\common\controller\Api;
use \app\common\model\integral\Exchange as ExchangeModel;
use app\common\model\integral\Goods as GoodsModel;
use app\common\model\integral\IntegralSku;
use app\common\model\seckill\Order;
use app\common\model\User;
use EasyWeChatComposer\EasyWeChat;
use Symfony\Component\HttpFoundation\Request;
use think\Db;
use think\Exception;
use think\Log;
use think\Hook;

/**
 * 兑换列表接口
 */
class Exchange extends Api
{

    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['details',"companyDetails",'pay','integralNot'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];

    public function index()
    {
        $user=$this->auth->getUser();
        //$user['id']=2;
        $page = !$this->request->param('page')?1:$this->request->param('page');//页
        $where="1=1";
        $status=$this->request->param('status');
        if((isset($status) || $status==0 )&& $status!='') $where=["status"=>$this->request->param('status')];
        if($status==8) $where=["status"=>5];
        $list=ExchangeModel::field("id,goods_id,status,status_remark,mode pay_data,all_money,actual_money,num,createtime,
        goods_details,out_trade_no,time_expire,address,company_name,company_code,numbers,specs_name,time_expire")
            ->where(["user_id"=>$user["id"]])
            ->where($where)
            ->order("createtime desc")
            ->paginate(10,'',['page' => $page, 'list_rows' => 10])
            ->each(function ($v){
                if((int)$v["status"]==0 && $v["time_expire"]<time()){
                    $v["over_status"]=1;
                    $exchang=ExchangeModel::get($v["id"]);
                    if($exchang){
                        Db::startTrans();
                        try {
                            $exchang->status = 2;
                            $exchang->status_remark = "用户超时未付款";
                            $exchang->cancel_time = time();
                            if ($exchang["specs_id"]) {
                                IntegralSku::where(["sku_id" => $exchang["specs_id"]])
                                    ->setInc("stock", $exchang["num"]);//加回库存
                            }
                            GoodsModel::where(["id" => $exchang["goods_id"]])
                                ->setInc("stock", $exchang["num"]);//加回库存
                            $exchang->save();
                            Db::commit();
                        } catch (Exception $e) {
                            Db::rollback();
                            $this->error($e->getMessage());
                        }
                    }
                }
                $v["time_expire"]=date("Y-m-d H:i:s",$v["time_expire"]);
                $v["createtime"]=date("Y-m-d H:i:s",$v["createtime"]);
                $v["goods_details"]=json_decode($v["goods_details"],true);
                $v["goods_details"]=$v["goods_details"]["allgoods"];
                $v["address"]=json_decode($v["address"],true);
                return $v;
            });
        $this->success('返回成功', $list);
    }
    /*
     * 订单详情
     * */
    public function details(){
        if(!$this->request->param('id')) $this->error("订单id为空","",2);
        $list=ExchangeModel::get(["id"=>$this->request->param('id')]);
        $list["goods_details"]=json_decode($list["goods_details"],true);
        $list["goods_details"]=$list["goods_details"]["allgoods"];
        $list["address"]=json_decode($list["address"],true);
        if((int)$list["status"]==0 && $list["time_expire"]<time()){
            $list["over_status"]=1;
            $exchang=ExchangeModel::get($list["id"]);
            if($exchang){
                Db::startTrans();
                try {
                    $exchang->status = 2;
                    $exchang->status_remark = "用户超时未付款";
                    $exchang->cancel_time = time();
                     if ($exchang["specs_id"]) {
                         IntegralSku::where(["sku_id" => $exchang["specs_id"]])
                             ->setInc("stock", $exchang["num"]);//加回库存
                     }
                     GoodsModel::where(["id" => $exchang["goods_id"]])
                         ->setInc("stock", $exchang["num"]);//加回库存
                    $exchang->save();
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
            }
        }
        if($list["time_expire"]) $list["time_expire"]=date("Y-m-d H:i:s",$list["time_expire"]);
        if($list["paytime"]) $list["paytime"]=date("Y-m-d H:i:s",$list["paytime"]);
        if($list["createtime"]) $list["createtime"]=date("Y-m-d H:i:s",$list["createtime"]);
        $this->success('返回成功', $list);
    }
    /*
     * 取消订单
     * */
    public function cancel()
    {
        if(!$this->request->param('id')) $this->error("订单id为空","",2);
        $exchang=ExchangeModel::get($this->request->param('id'));
        //状态:0=待支付,1=已支付,2=已取消,3=待发货,4=已发货,5=确认收货,6=维权
        if(empty($exchang)) $this->error("订单不存在","",2);
        if((int)$exchang["status"]!=0) $this->error("订单状态错误","",2);
       /* if($this->request->param('status_remark')) $this->error("取消理由为空","",2);
        ExchangeModel::update(["status"=>2,"status_remark"=>$this->request->param('status_remark'),"cancel_time"=>time()],["id"=>$exchang["id"]]);
       */
        Db::startTrans();
        try {
            $exchang->status = 2;
            $exchang->status_remark = "用户取消订单";
            $exchang->cancel_time = time();
            $exchang->save();
            if(!empty($exchang["specs_id"])) IntegralSku::where(["id"=>$exchang["goods_id"]])->setDec("stock",1); //第一场抢购多规格
            GoodsModel::where(["id"=>$exchang["goods_id"]])->setDec("stock",1);//第一场抢购;
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
    public function  receipt(){
        if(!$this->request->param('id')) $this->error("订单id为空","",2);
        $exchang=ExchangeModel::get($this->request->param('id'));
        //状态:0=待支付,1=已支付,2=已取消,3=待发货,4=已发货,5=确认收货,6=维权
        if((int)$exchang["status"]!=4) $this->error("订单状态错误","",2);
        $exchang->status="8";
        $exchang->rgoodstime=time();
        $exchang->save();
        $this->success('确认收货成功');
    }
    /*
     * 查询物流
     * */
    public function companyDetails(){
        /*if(!$this->request->param('id')) $this->error("快递单号为空","",2);
        if(!$this->request->param('code')) $this->error("快递公司编码为空","",2);
        include_once CONF_PATH . 'api' . DS . 'Kuaidi_Query.php';
        //$k = new \Kuaidi_Query(75458726673075,"zhongtong");
        $k = new \Kuaidi_Query(75458726673075,"zhongtong");
        $data = $k->Query();
        if ($data['message'] == "ok") {
            $list = $data['data'];
            $this->success('返回成功',$list);
        }else{
            $this->error($data['message'],'',2);
            var_dump($data['message']);
        }*/
        if(!$this->request->param('id')) $this->error("订单为空","",2);
        if(!$this->request->param('type')) $this->error("订单类型为空","",2);
        $type=$this->request->param('type');
        if($type==1)
        {
            //积分商城订单
            $company=ExchangeModel::field("company_name,company_code,numbers")
                ->where(["id"=>$this->request->param("id")])->find();
        }
        if($type==2)
        {
            //抢购订单
            $company=Order::field("company_name,company_code,numbers")
                ->where(["id"=>$this->request->param("id")])->find();
        }
        if(!$company || empty($company["numbers"]) || empty($company["company_code"])) $this->error("订单不存在","",2);
        include_once CONF_PATH . 'api' . DS . 'Kuaidi_Query.php';
        //$k = new \Kuaidi_Query(75458726673075,"zhongtong");
        $k = new \Kuaidi_Query($company["numbers"],$company["company_code"]);
        $data = $k->Query();
        if ($data['message'] == "ok") {
            $list = $data['data'];
            $company["state"]=$data["state"];
            $this->success('返回成功',["list"=>$list,"top"=>$company]);
        }else{
            $this->error($data['message'],'',2);
        }
    }
    /*
     * 支付
     * */
    public function pay(){
        if(!$this->request->param('id')) $this->error("订单id为空","",2);
        $user=$this->auth->getUser();
        //$user["id"]=2;
        $user=User::get($user["id"]);
        $id=$this->request->param('id');
        $exchang=ExchangeModel::get($this->request->param('id'));
        if((int)$exchang["status"]!=0) $this->error("订单状态错误","",2);
        // 判断用户是否实名
        if($user -> real_status == 0) $this->error("用户未实名，即将跳转到实名页面",'',3);
        // 判断用户是否设置支付密码
        if(!$user -> pay_pwd) $this->error("用户未设置支付密码，即将跳转到设置支付密码",'',4);
        if($user["money"]<$exchang["all_money"] && (int)$exchang["mode"]==1) $this->error('余额不足', '', 2);
        if($user["score"]<$exchang["all_money"] && (int)$exchang["mode"]==0) $this->error('积分不足', '', 2);
        if($exchang["time_expire"]<time()){
            //状态:0=待支付,1=已支付,2=已取消,3=待发货,4=已发货,5=确认收货,6=维权,7=待选择,8=已完成
            Db::startTrans();
            try {
                $exchang->status = 2;
                $exchang->status_remark = "用户超时未付款";
                if ($exchang["specs_id"]) {
                    IntegralSku::where(["id" => $exchang["specs_id"]])->setInc("stock", $exchang["num"]);//加回库存
                }
                GoodsModel::where(["id" => $exchang["goods_id"]])->setInc("stock", $exchang["num"]);//加回库存
                $exchang->save();
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->error("订单已超时", "", 2);
        }
        if(empty($exchang["specs_id"]))
        {
            //没有规格的商品订单
            $goods=GoodsModel::where("stock>0")
                ->where(["id"=>$exchang["goods_id"],"status"=>0])->lock(true)
                ->find();
            if(!$goods){
                //状态:0=待支付,1=已支付,2=已取消,3=待发货,4=已发货,5=确认收货,6=维权
                ExchangeModel::where($id)->update([
                    "status"=>2,
                    "status_remark"=>"商品已售完",
                ]);
                $this->error("商品已售完","",2);
            }
            if($exchang["mode"]==0){
                /*积分兑换*/
                if(!$this->request->param('pay_pwd')) $this->error("支付密码为空","",2);
                if(md5(md5($this->request->param('pay_pwd')) . $user["pay_salt"])!=$user["pay_pwd"]) $this->error("支付密码错误","",2);
                Db::startTrans();
                try {
                    $exchang->status="3";
                    $exchang->paytime=time();
                    $exchang->save();
                    /*ExchangeModel::where(["id"=>$id])
                        ->update([
                        "status"=>4,
                        "paytime"=>time(),
                        ]);*/
                    User::score(-($exchang["all_money"]),$user["id"],"兑换商品");
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ( $user['level'] == 1 ){
                    Hook::exec('app\\api\\controller\\activity\\Bonus','createBonusInfo',$user["id"]);
                }

                $this->success("兑换成功");
            }
            if($exchang["mode"]==1){
                //余额支付
                if($exchang["mode"]==0 && !$this->request->param('pay_pwd')) $this->error("支付密码为空","",2);
                if(md5(md5($this->request->param('pay_pwd')) . $user["pay_salt"])!=$user["pay_pwd"]) $this->error("支付密码错误","",2);
                Db::startTrans();
                try {
                    $exchang->status="3";
                    $exchang->paytime=time();
                    $exchang->save();
                   /* ExchangeModel::where(["id"=>$id])
                        ->update([
                            "status"=>4,
                            "paytime"=>time(),
                        ]);*/
                    User::money(-($exchang["all_money"]),$user["id"],"兑换商品");
                    Db::commit();
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                $this->success("兑换成功");
            }
            $details=json_decode($exchang["goods_details"],true);
            if($exchang["mode"]==2)
            {
                //微信支付
                $out_trade_no=Pay::build_order_no();
                GoodsModel::where(["id"=>$id])->update(["out_trade_no"=>$out_trade_no]);
                /*$list=Pay::wx_pay("积分兑换",$out_trade_no,$exchang["all_money"],
                'https://yimei.sctqmt.com/api/integral.Exchange/integralNot');
                $this->success("",$list);*/
                $response=Service::submitOrder($exchang["all_money"]*100,
                    $out_trade_no, "alipay", $details["name"],
                    $this->request->domain() . 'api/integral.Exchange/integralNot/paytype/wechat',
                    $this->request->domain() . 'api/integral.Exchange/integralNot/paytype/wechat',
                    "app");
            }
            if($exchang["mode"]==3)
            {
                //支付宝支付
                $out_trade_no=Pay::build_order_no();
                GoodsModel::where(["id"=>$id])->update(["out_trade_no"=>$out_trade_no]);
                $response=Service::submitOrder($exchang["all_money"],
                    $out_trade_no, "alipay", $details["name"],
                    $this->request->domain() . 'api/integral.Exchange/integralNot/paytype/alipay',
                    $this->request->domain() . 'api/integral.Exchange/integralNot/paytype/alipay',
                    "app");
                /*$list=Pay::alipay("积分兑换",$out_trade_no,$exchang["all_money"],'https://yimei.sctqmt.com/api/integral.Exchange/integralAlipayNot');
                $this->success("",$list);*/
            }
        }
         if((int)$exchang["mode"]==0 || (int)$exchang["mode"]==1){
             /*积分兑换*/
             /*$user["pay_salt"]="51f4rP";
             $user["pay_pwd"]="8594f8a7b41901bb2edf429d533a088e";*/
             $pwd=$this->request->param('pay_pwd');
             if(!$this->request->param('pay_pwd')) $this->error("支付密码为空","",2);
             if($user["pay_pwd"] != $this -> auth -> getEncryptPassword($pwd,$user["pay_salt"])) $this->error("支付密码错误","",2);
             Db::startTrans();
             try {
                 /*ExchangeModel::where(["id"=>$id])
                     ->update([
                         "status"=>4,
                         "paytime"=>time(),
                     ]);*/
                 $exchang->status="3";
                 $exchang->paytime=time();
                 $exchang->save();
                 if((int)$exchang["mode"]==0 ){
                     /*积分兑换*/
                     User::score(-($exchang["all_money"]),$user["id"],"兑换商品");
                 }else{
                     User::money(-($exchang["all_money"]),$user["id"],"兑换商品");
                 }
                 Db::commit();
             } catch (Exception $e) {
                 Db::rollback();
                 $this->error($e->getMessage());
             }
             $this->success("兑换成功");
         }
        if((int)$exchang["pay_data"]==2)
        {
            //微信
            $out_trade_no=Pay::build_order_no();
            GoodsModel::where(["id"=>$id])->update(["out_trade_no"=>$out_trade_no]);
          /*  $list=Pay::wx_pay("兑换商品",$out_trade_no,$order["all_money"],'');
            $this->success("",$list);*/
            $response=Service::submitOrder($exchang["all_money"]*100,
                $out_trade_no, "alipay", $details["name"],
                $this->request->domain() . 'api/integral.Exchange/integralNot/paytype/wechat',
                $this->request->domain() . 'api/integral.Exchange/integralNot/paytype/wechat',
                "app");
        }
        if((int)$exchang["pay_data"]==3)
        {
            //支付宝支付
            $out_trade_no=Pay::build_order_no();
            GoodsModel::where(["id"=>$id])->update(["out_trade_no"=>$out_trade_no]);
            /*$list=Pay::alipay("兑换商品",$out_trade_no,$order["all_money"],'');
            $this->success("",$list);*/
            $response=Service::submitOrder($exchang["all_money"]*100,
                $out_trade_no, "alipay", $details["name"],
                $this->request->domain() . 'api/integral.Exchange/integralNot/paytype/alipay',
                $this->request->domain() . 'api/integral.Exchange/integralNot/paytype/alipay',
                "app");
        }

    }
    /**
     * 支付宝回调签名验证
     * @param  array $data 微信回调返回的原始xml数据
     * @return bool
     */
    public  function checkSign($data)
    {
        include EXTEND_PATH . 'Alipay/aop/AopClient.php';
        $aop = new \AopClient();
        $alipayrsaPublicKey ="MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAjtduqhHMbNG9f3D6Dwn1S88v0RO/5OujpZNalCmxMnk6b4r9DS1IfxHga+SylzYAUy+W4qMM04IIUtNDjBufQgSLW/8OO+144RG+JCyzh1uj2uCWXih3C6QKUnbm1hqm4OIEuqJjRoxQTRhR+gARQcbf/oRj0PC1kwz9bkM+UnBUyp94dMZtH6EJPgcRgW7YsU2p1/WjfeOat+T4MmTXpCfEWP78sK7mzIzzCldKgR6oZlP82ZD0pVXzkLE3EXhuTxn8dhAY5M55dU9v1CcEy3bbdjweYawLbbnEGgrJ8TK8oHnV+ZYhP6UgP/2v/Thkqn5ZBl4X5Aqjz3wm1FgkMwIDAQAB"
        ; $result = $aop->rsaCheckV1($data, $alipayrsaPublicKey, 'RSA2');
        return $result;
    }
    /*回调*/
    public function integralNot(){
        $request = Request::createFromGlobals();
        $data = $request->request->count() > 0 ? $request->request->all() : $request->query->all();
        Log::write($data,'notice');
        try {
            /*$payamount = $paytype == 'alipay' ? $data['total_amount'] : $data['total_fee'] / 100;*/
            $out_trade_no = $data['out_trade_no'];
            $exchange = ExchangeModel::where('out_trade_no',$out_trade_no)->find();
            $exchange->status = '3';//修改订单状态为待发货
            $exchange->paytime = time();
            //$goods->out_trade_no = $data["out_trade_no"];
            $exchange->save();
            //你可以在此编写订单逻辑
        } catch (Exception $e) {
        }
        die();

        $paytype = $this->request->param('paytype');
        Log::write($paytype,'notice');
        $pay = Service::checkNotify($paytype);
        Log::write($pay,'notice');
        if (!$pay) {
            echo '签名错误';
            return;
        }
        $data = $pay->verify();
        try {
            /*$payamount = $paytype == 'alipay' ? $data['total_amount'] : $data['total_fee'] / 100;*/
            $out_trade_no = $data['out_trade_no'];
            $exchange = ExchangeModel::where('out_trade_no',$out_trade_no)->find();
            $exchange->status = '3';//修改订单状态为待发货
            $exchange->paytime = time();
            //$goods->out_trade_no = $data["out_trade_no"];
            $exchange->save();
            //你可以在此编写订单逻辑
        } catch (Exception $e) {
        }
        die();

        //if (!WeChat::checkSign($data)) {
        if (!EasyWeChat::getEncryptionKey($data)) {
            write_log(['msg' => '微信回调签名错误', 'data' => $data], __DIR__);
            return false;
        }
        $exchange = ExchangeModel::where('out_trade_no', $data['out_trade_no'])->find();
        if (!$exchange || !empty($exchange['status'])) {
            return false;
        }
        //状态:0=待支付,1=已支付,2=已取消,3=待发货,4=已发货,5=确认收货,6=维权
        $exchange->status = 3;//修改订单状态为待发货
        $exchange->paytime = time();
        //$goods->out_trade_no = $data["out_trade_no"];
        $exchange->save();
        echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';

    }

    /*
     * 支付宝回调
     * */
    public function integralAlipayNot(){
        $paytype = $this->request->param('paytype');
        $pay = Service::checkNotify($paytype);
        if (!$pay) {
            echo '签名错误';
            return;
        }
        $data = $pay->verify();
        try {
            /*$payamount = $paytype == 'alipay' ? $data['total_amount'] : $data['total_fee'] / 100;*/
            $out_trade_no = $data['out_trade_no'];
            $exchange = ExchangeModel::where('out_trade_no',$out_trade_no)->find();
            $exchange->status = 3;//修改订单状态为待发货
            $exchange->paytime = time();
            //$goods->out_trade_no = $data["out_trade_no"];
            $exchange->save();
            //你可以在此编写订单逻辑
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
            $exchange = ExchangeModel::where('out_trade_no',$trade_no)->find();
            $exchange->status = 3;//修改订单状态为待发货
            $exchange->paytime = time();
            //$goods->out_trade_no = $data["out_trade_no"];
            $exchange->save();
        } else {
            //支付失败
        }
    }

}
