<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\agent\GiftOrder;
use app\common\model\integral\Exchange;
use app\common\model\integral\Goods;
use app\common\model\integral\IntegralSku;
use app\common\model\seckill\Order;
use app\common\model\seckill\Preorder;
use think\Db;
use think\Exception;

/**
 * 首页接口
 */
class Task extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /*
     * 取消订单
     * 30分钟执行一次
     * */
    public function cancel(){
        //状态:0=待支付,1=已支付,2=已取消,3=待发货,4=已发货,5=确认收货,6=维权
        $exchange=Exchange::whereTime('time_expire', ">=",'-30 minute')->where(["status"=>0])->select();
        if($exchange){
            Db::startTrans();
            try {
            foreach ($exchange as $v){
                Exchange::update([
                    "status"=>2,
                    "cancel_time"=>time(),
                    "status_remark"=>"超时未付款",
                ],["id"=>$v["id"]]);
                if ($v["specs_id"]) {
                    IntegralSku::where(["id" => $v["specs_id"]])->setInc("stock", 1);//加回库存
                }
                Goods::where(["id" => $v["goods_id"]])->setInc("stock", 1);//加回库存
                Db::commit();
                }
            }catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
        }
    }

    /*
    * 订单自动确认收货
     * 一天执行一次
    * */
    public function receipt(){
        //状态:0=待支付,1=已支付,2=已取消,3=待发货,4=已发货,5=确认收货,6=维权
        $exchange=Exchange::where(["status"=>4])->select();
        if($exchange){
        foreach ($exchange as $v){
            if(date("Ymd",$v["auto_time"])==date("Ymd",time())){
                Exchange::update([
                   "status"=>8,
                   "rgoodstime"=>time(),
                   "status_remark"=>"自动确认收货",
                ],["id"=>$v["id"]]);
            }
        }
        }
        $order=Order::where(["status"=>4])->select();
        if($order){
            foreach ($order as $v){
                if(date("Ymd",$v["auto_time"])==date("Ymd",time())){
                    Exchange::update([
                        "status"=>5,
                        "rgoodstime"=>time(),
                        "status_remark"=>"自动确认收货",
                    ],["id"=>$v["id"]]);
                }
            }
        }
    }
    /*
     * 预售订单--取消订单
     * 一天执行一次
     * */
    public function precancel(){
        $order=Preorder::where(["status"=>0])->select();
        if($order){
            foreach ($order as $v){
                    if($order["start_time"]<=time() && time()<$order["end_time"] &&date("Ymd",$v["end_time"])==date("Ymd",time())){
                        Preorder::update([
                           "status"=>2,
                            "status_remark"=>"用户未及时付款，系统自动取消订单",
                            "cancel_time"=>time(),
                        ],["id"=>$v["id"]]);
                    }
            }
        }
        $giforder=GiftOrder::where(["status"=>0])->select();
        if($giforder){
            foreach ($giforder as $v){
                Preorder::update([
                    "status"=>2,
                    "status_remark"=>"用户未及时付款，系统自动取消订单",
                    "cancel_time"=>time(),
                ],["id"=>$v["id"]]);
            }
        }
    }
}