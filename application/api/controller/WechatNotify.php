<?php


namespace app\api\controller;


use app\api\controller\integral\Exchange;
use app\api\controller\seckill\Order;

/**
 * 微信支付回调
 * Class WechatNotify
 * @package app\api\controller
 */
class WechatNotify
{
    /**
     * 积分商城微信支付回调
     */
    public function integralNot()
    {
        $xmldata = file_get_contents('php://input');
        $data = (array)simplexml_load_string($xmldata, 'SimpleXMLElement', LIBXML_NOCDATA);  //解析xml
        Exchange::integralNot($data);
    }
    /**
     * 抢购微信支付回调
     */
    public function seckillNot()
    {
        $xmldata = file_get_contents('php://input');
        $data = (array)simplexml_load_string($xmldata, 'SimpleXMLElement', LIBXML_NOCDATA);  //解析xml
        Order::seckillNot($data);
    }
}