<?php
namespace app\api\controller;

use think\Db;

class WxPay{
    /*
     * 小程序微信支付
     */
    protected $appid; //
    protected $mch_id; //
    protected $key;
    protected $openid; //
    protected $out_trade_no; //商户订单号
    protected $body;//统一下单名称
    protected $total_fee; // 价格/分 100*100
    protected $notify_url;//获取ip地址
    public function __construct($openid="",$total_fee="") {
        $appid = "wx241ff50e9e7f571c";
        $mch_id = '10058689';
        $key = "3eecc50066cada72ad29a36a26169f4a";
        $out_trade_no = date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);;
        $body =  '购买商品';
        $this->appid = $appid;
        $this->openid =  $openid;
        $this->mch_id =  $mch_id;
        $this->key = $key;
        $this->out_trade_no =$out_trade_no;
        $this->body =$body;
        $this->total_fee = $total_fee*100;
        $this->notify_url = $this->getRequestIp();
    }


    private function getRequestIp() {
        return request()->ip();
    }

    public function pay($notify_url=null) {
        //统一下单接口
        $arr = $this->weixinapp($notify_url);
        return HttpSuccess(json_decode($arr));
    }

    //微信小程序接口
    private function weixinapp($notify_url=null) {
        //统一下单接口
        $unifiedorder = $this->unifiedorder($notify_url);
        dump($unifiedorder);die();
        $parameters = array(
            'appId' => $this->appid, //小程序ID
            'timeStamp' => '' . time() . '', //时间戳
            'nonceStr' => $this->createNoncestr(), //随机串
            'package' => 'prepay_id=' . $unifiedorder['prepay_id'], //数据包
            'signType' => 'MD5'//签名方式
        );
        //dump($parameters);
        //签名
        $parameters['paySign'] = $this->getSign($parameters);
        return json_encode($parameters);
    }
    //统一下单接口
    private function unifiedorder($notify_url=null) {
        $url = 'https://api.mch.weixin.qq.com/v3/pay/transactions/jsapi';
        $parameters = array(
            'appid' => $this->appid, //小程序ID
            'mch_id' => $this->mch_id, //商户号
            'nonce_str' => $this->createNoncestr(), //随机字符串
            'body' => $this->body,
            'out_trade_no'=> $this->out_trade_no,
            'total_fee' => $this->total_fee,
            'spbill_create_ip' => $this->notify_url, //终端IP
            'notify_url' => $notify_url, //通知地址  确保外网能正常访问
            'openid' => $this->openid, //用户id
            'trade_type' => 'JSAPI'//交易类型
        );

        //统一下单签名
        $parameters['sign'] = $this->getSign($parameters);
        $xmlData = $this->arrayToXml($parameters);
        $return = $this->xmlToArray($this->postXmlCurl($xmlData, $url, 60));
        //dump($return);
        return $return;
    }

    function a(){
        return config('server_host').'applet.php/applet/v1/wx/notify_url';
        //return 'www.baidu.com';
    }
    private static function postXmlCurl($xml, $url, $second = 30)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); //严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        set_time_limit(0);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            throw new WxPayException("curl出错，错误码:$error");
        }
    }
    //数组转换成xml
    private function arrayToXml($arr) {
        $xml = "<root>";
        foreach ($arr as $key => $val) {
            if (is_array($val)) {
                $xml .= "<" . $key . ">" . arrayToXml($val) . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            }
        }
        $xml .= "</root>";
        return $xml;
    }
    //xml转换成数组
    private function xmlToArray($xml) {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $val = json_decode(json_encode($xmlstring), true);
        return $val;
    }
    //作用：产生随机字符串，不长于32位
    private function createNoncestr($length = 32) {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }
    //作用：生成签名
    private function getSign($Obj) {
        foreach ($Obj as $k => $v) {
            $Parameters[$k] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        //签名步骤二：在string后加入KEY
        $String = $String . "&key=" . $this->key;
        //签名步骤三：MD5加密
        $String = md5($String);
        //签名步骤四：所有字符转为大写
        $result_ = strtoupper($String);
        return $result_;
    }
    //作用：格式化参数，签名过程需要使用
    private function formatBizQueryParaMap($paraMap, $urlencode) {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if ($urlencode) {
                $v = urlencode($v);
            }
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar;
        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff) - 1);
        }
        return $reqPar;
    }
}