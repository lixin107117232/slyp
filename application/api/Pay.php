<?php
namespace app\api\controller;

class Pay{
    protected $_site=[];
    public function __construct(){
        $site = \think\Config::get("site");
        $this->_site=$site;
    }
    public function wx_pay($body_name='',$order_number='',$fee='',$notify_url='') {
        $appid = $this->_site["wx_appid"]; //应用的appid
        $mch_id = $this->_site["wx_mch_id"]; // 您的商户账号
        $nonce_str = $this -> nonce_str(); //随机字符串
        $body = $body_name; // 举例: 服务预约
        $out_trade_no = $order_number; //商户订单号
        $total_fee = $fee*100;
        $spbill_create_ip = '122.114.62.70'; // IP白名单
        $notify_url =$notify_url; // 回调的url【自己填写,如若回调不成功请注意查看服务器是否开启防盗链,回调地址用http】
        $trade_type = 'APP'; //交易类型 默认

        //这里是按照顺序的 因为下面的签名是按照顺序 排序错误 肯定出错

        $post['appid'] = $appid;
        $post['body'] = $body;
        $post['mch_id'] = $mch_id;
        $post['nonce_str'] = $nonce_str; //随机字符串
        $post['notify_url'] = $notify_url;
        $post['out_trade_no'] = $out_trade_no;
        $post['spbill_create_ip'] = $spbill_create_ip; //终端的ip
        $post['total_fee'] = $total_fee; //总金额 最低为一块钱 必须是整数
        $post['trade_type'] = $trade_type;
        $sign = $this -> sign($post); //签名
        dump($sign);die();

        $post_xml = "<xml>
                           <appid><![CDATA[$appid]]></appid>
                           <body><![CDATA[$body]]></body>
                           <mch_id><![CDATA[$mch_id]]></mch_id>
                           <nonce_str><![CDATA[$nonce_str]]></nonce_str>
                           <notify_url><![CDATA[$notify_url]]></notify_url>
                           <out_trade_no><![CDATA[$out_trade_no]]></out_trade_no>
                           <spbill_create_ip><![CDATA[$spbill_create_ip]]></spbill_create_ip>
                           <total_fee><![CDATA[$total_fee]]></total_fee>
                           <trade_type><![CDATA[$trade_type]]></trade_type>
                           <sign><![CDATA[$sign]]></sign>
                        </xml>";

        //统一接口prepay_id

        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $xml = $this -> http_request($url, $post_xml);

        $array = $this -> xml($xml); //全要大写
//            dump($array);die;
        if ($array['RETURN_CODE'] == 'SUCCESS' && $array['RESULT_CODE'] == 'SUCCESS') {
            $time = time();
            $tmp = []; //临时数组用于签名
            $tmp['appid'] = $appid;
            $tmp['noncestr'] = $nonce_str;
            $tmp['package'] = 'Sign=WXPay';
            $tmp["partnerid"] = $mch_id;
            $tmp['prepayid'] = $array['PREPAY_ID'];
            $tmp['timestamp'] = "$time";

            $data['appid'] = $appid;
            $data['noncestr'] = $nonce_str; //随机字符串
//                $data['package'] = 'prepay_id='.$array['PREPAY_ID']; //统一下单接口返回的 prepay_id 参数值，提交格式如：prepay_id=*
            $data['package'] = "Sign=WXPay";
            $data['partnerid']= $mch_id;
            $data['prepayid'] = $array['PREPAY_ID'];
            $data['timestamp'] = "$time"; //时间戳
            $data['sign'] = $this ->sign($tmp);//签名

        } else {
            $data['status'] = 0;
            $data['text'] = "错误";
            $data['RETURN_CODE'] = $array['RETURN_CODE'];
            $data['RETURN_MSG'] = $array['RETURN_MSG'];
        }
        return json_encode($data);
    }
    public function build_order_no(){
        return date('ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
    }
    public function sign($data,$wx_key)
    {
        //签名 $data要先排好顺序
        $stringA = '';
        ksort($data);
        foreach($data as $key => $value) {
            if (!$value) continue;
            if ($stringA)
                $stringA.= '&'.$key."=".$value;
            else
                $stringA = $key."=".$value;
        }

        $stringSignTemp = $stringA.'&key='.'66666666666666664564654444412'; //申请支付后有给予一个商户账号和密码，登陆后自己设置key
        return strtoupper(md5($stringSignTemp));
    }


    //随机32位字符串
    private function nonce_str() {
        $result = '';
        $str = 'QWERTYUIOPASDFGHJKLZXVBNMqwertyuioplkjhgfdsamnbvcxz';
        for ($i = 0; $i < 32; $i++) {
            $result.= $str[rand(0, 48)];
        }
        return $result;
    }

    //curl请求啊
    public function http_request($url, $data = null, $headers = array()) {
        $curl = curl_init();
        if (count($headers) >= 1) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($curl, CURLOPT_URL, $url);

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);

        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    //获取xml
    private function xml($xml) {
        $p = xml_parser_create();
        xml_parse_into_struct($p, $xml, $vals, $index);
        xml_parser_free($p);
        $data = "";
        foreach($index as $key => $value) {
            if ($key == 'xml' || $key == 'XML') continue;
            $tag = $vals[$value[0]]['tag'];
            $value = $vals[$value[0]]['value'];
            $data[$tag] = $value;
        }
        return $data;
    }


    /*
      * 支付宝支付
      * $body            名称
      * $total_amount    价格
      * $product_code    订单号
      * $notify_url      异步回调地址
      */
    public function alipay($body, $total_amount, $product_code, $notify_url)
    {

        /**
         * 调用支付宝接口。
         */
        import('.Alipay.aop.AopClient', '', '.php');
        import('.Alipay.aop.request.AlipayTradeAppPayRequest', '', '.php');
        $aop = new \AopClient();

        $aop->gatewayUrl         = "https://openapi.alipay.com/gateway.do";
        $aop->appId              = $this->_site->ali_appid;
        $aop->rsaPrivateKey      ='MIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQD5BiJM8Q3VtwGkRbo/VJL8BKm9S4jjBfNavkumjF22Eq0ISUb4wBWcy6ydOwme6CNqxIwSjipCjqPlHKNVrJWyeBIn0al3mVxZ0IDIClGEqRHdmvYGPT9/xo64e0gSRbcMl3Vr/f3tANaKO6UyegilPSQMS0TbbFiQZEkyXO+pHI8pyHc68tYIwIrCU794vRFTkxrSfDXHKRQowSIIolP7u0MaPXyeUmfUI0bRPbbXmaqLTbh+BUn0lHGN6QAwJqTS2pP9GpcRP0InvrLp9ipzdbEsxB0KTnhbZtZSSakwdgJmswMIzwELVBSsPJSuhGQg0LtioB2svPGj+d7NSMi7AgMBAAECggEBAPXqb88JsX7edbmSviUyUOCdfj4YLLr8smBnUe/L5/MYqFVpf7PAhNdNb03p8ktBtVAHfsgIKoWFtSZZTJcbks0ms88sxiz8fu2W8MYbInteNu1fzRtGOsHlBCX8YKTiwaymmWem8K6uyC7EThP13TnIkiOt5PbHHQKidoJMssONJMUxJrF569oIIB8pn3mV21J1HyN0x7UF2h/PXU1DkVWDqokl0l9y3FEVcJ+rsMZT0WPHKiszP45cuzz9LfDDiFkxJI3iUsP5F9YEaDGjEw0D8bM0ywkyL3mDSpmSF/+tZ5IQvKo/JVmumqszhPtiQSqU/o5y84GIf30WPlW2SZkCgYEA/pQIbo0F5XCNvqP6KYcBcsjrxQMCuG0PddgLBaXUomxtsBi1ATifwh1T8bDUac3aU0EpLSQhWnu/WNpXyswvhwwcV879JwrWx2nO0D638mxzzn94+Q8lgHlafQmYJlZlelAHILyGYlx40cUK/FakLCNwFN4ocWao+M9ObUEMBjcCgYEA+moo/8fmN9CJnw4/c+fk41bmuodUU5YBPV2k5VV7TwnMgg2dr4vbMVh8jTta+eBTjpcIYILOGAKYd5u7uie6ZuZiumXmmFnOyGy+hew1R9BMSms5zGSkYhRD2EgiB184FAzgUOZGQYrHKms/2adnyx0SpGxXKpOtnyrvA8XyT50CgYEA3l41w0LhXJlk5pna4L0xUa8Y6hyIGsoAkCHm9sb0Je/qG8BpEqkAOxFdCqc30zdhNgmbyvddPukKqbUGrHiQJzk35KdDzv+Tvdm5MYMnL9T1jvEfnQVS75aQqNlhklMzDpSqtTiXdYFqc2jXALU5b+iAdWncD7npbHPAAISp2R8CgYACedo97TQRiTZTJEjsVHam6M0POxdSXEFW4f4nZlj5xxcGkivk+HUKX92bZ+LWZalt14B1s9Vl12C6jgelJ49oRQ7k2O0WxIyO3sRjfppoQ179vWGs67HUZm7lTJFJkV90k0wEgMJhE4Y0nSrcdBNKptbwWUHjYeJtmHcUiniC4QKBgQCUiCa+mPRBNeOpE6n6GeHtpJ0A/KMNeynvlVwF7DvIaxKbS8F6Ii4oCOnIueCMQk39Njg253IobWzKr8ilJtlUL0/OGMWDEgC18vbu6TwuSr8BfTAD6S0OaNwPUOuxC/ZOGTei83DL+H/uv01rswyYqdNLtNpBd3aFfc+2/kI8lQ==';//$this->_site->ali_rsaPrivateKey;
        $aop->format             = "json";
        $aop->charset            = "UTF-8";
        $aop->signType           = "RSA2";
        $aop->alipayrsaPublicKey = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzaVLQCpos3cDpoKjxpCqMUAZmymmWKGo1b59ZIAQt633GZ0Wmi/Q5LWR+F1eACdae4s8cSUt9EgEmkQkeR5KUK+bUa3P91//dEtnXn0pudaVqq8lwi/mAMMln77xiTmvuA1UL/f3Rd+qdj6t3GMSx138joFmdekcBTU46m0TqiFHsYRjNiioVc3OCZY2H0DkfzY9IAzUZ7q6NQ3S13yK1AkJ/YtLAVIoFzgZWZiX0lBWjJA6JOeF83z0ALDBSlYEiAwgzx52W3BD2LOgrGP0kdz/BjYSX5/5kSLDfrZi49LJbZriTTwbIjwva/YdQAkAPIDQ+iacYmUIIbuvhZznpwIDAQAB";//$this->_site->ali_alipayrsaPublicKey;
        $aop->apiVersion         = "1.0";

        $request = new \AlipayTradeAppPayRequest();
        $arr['body']                = $body;
        $arr['subject']             = $body;
        $arr['out_trade_no']        = $product_code;
        $arr['timeout_express']     = '30m';
        $arr['total_amount']        = floatval($total_amount);
        $arr['product_code']        = 'QUICK_MSECURITY_PAY';
        $json = json_encode($arr);
        $request->setNotifyUrl($notify_url);
        $request->setBizContent($json);

        $response = $aop->sdkExecute($request);
        return $response;
    }
}