<?php

namespace app\common\library;

use app\common\model\Config;
use GuzzleHttp\Psr7;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use think\Hook;
use think\Log;



/**
 * 短信验证码类
 */
class Sms
{

    /**
     * 验证码有效时长
     * @var int
     */
    protected static $expire = 120;

    /**
     * 最大允许检测的次数
     * @var int
     */
    protected static $maxCheckNums = 10;

    /**
     * 获取最后一次手机发送的数据
     *
     * @param   int    $mobile 手机号
     * @param   string $event  事件
     * @return  Sms
     */
    public static function get($mobile, $event = 'default')
    {
        $sms = \app\common\model\Sms::
        where(['mobile' => $mobile, 'event' => $event])
            ->order('id', 'DESC')
            ->find();
        Hook::listen('sms_get', $sms, null, true);
        return $sms ? $sms : null;
    }

    /**
     * 发送验证码
     *
     * @param   int    $mobile 手机号
     * @param   int    $code   验证码,为空时将自动生成4位数字
     * @param   string $event  事件:regist注册,login登录,updata_phone修改手机号,update_pwd重置密码,update_pay_pwd重置支付密码,add_back新增银行卡
     * @return  boolean
     */
    public static function send($mobile, $code = null, $event = 'default')
    {
        $code = is_null($code) ? mt_rand(1000, 9999) : $code;
        $time = time();
        $ip = request()->ip();
        $num = \app\common\model\Sms::where('mobile',$mobile)
            ->where('event',$event)
            -> find();
        if($num){
            $num -> code = $code;
            $num -> createtime = $time;
            $num -> save();
        }else{
            \app\common\model\Sms::create(['event' => $event, 'mobile' => $mobile, 'code' => $code, 'ip' => $ip, 'createtime' => $time]);
        }

        $result = Sms::huawei($mobile,$code,$event);
//        $res = Hook::listen('sms_send', $sms, null, true);
        if ($result) {
            return $code;
        }else{
            return false;
        }

    }

    /**
     * 发送通知
     *
     * @param   mixed  $mobile   手机号,多个以,分隔
     * @param   string $msg      消息内容
     * @param   string $template 消息模板
     * @return  boolean
     */
    public static function notice($mobile, $msg = '', $template = null)
    {
        $params = [
            'mobile'   => $mobile,
            'msg'      => $msg,
            'template' => $template
        ];
        $result = Hook::listen('sms_notice', $params, null, true);
        return $result ? true : false;
    }

    /**
     * 校验验证码
     *
     * @param   int    $mobile 手机号
     * @param   int    $code   验证码
     * @param   string $event  事件:regist注册,login登录,updata_phone修改手机号,update_pwd重置密码,update_pay_pwd重置支付密码,add_back新增银行卡"
     * @return  boolean
     */
    public static function check($mobile, $code, $event = 'default')
    {
        $time = time() - self::$expire;
        $sms = \app\common\model\Sms::where(['mobile' => $mobile, 'event' => $event,'code' => $code])
            ->find();
        if ($sms) {
            if ($sms['createtime'] > $time && $sms['times'] <= self::$maxCheckNums) {
                $correct = $sms['code'];

                if (!$correct) {
                    $sms->times = $sms->times + 1;
                    $sms->save();
                    return false;
                } else {
                    return true;
                }
            } else {
                // 过期则清空该手机验证码
                self::flush($mobile, $event);
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 清空指定手机号验证码
     *
     * @param   int    $mobile 手机号
     * @param   string $event  事件
     * @return  boolean
     */
    public static function flush($mobile, $event = 'default')
    {
        \app\common\model\Sms::
        where(['mobile' => $mobile, 'event' => $event])
            ->delete();
        Hook::listen('sms_flush');
        return true;
    }

    /**
     *
     * @param string $receiver  手机号
     * @param string $code  验证码
     * @param string $event  事件:regist注册,login登录,updata_phone修改手机号,update_pwd重置密码,update_pay_pwd重置支付密码"
     * @return boolean
     *
    */
    public static function huawei($receiver,$code,$event){
        if($event == 'regist' && $event == 'login' && $event == 'updata_phone' && $event == 'update_pwd' && $event == 'update_pay_pwd'){
            return false;
        }
        require_once dirname(dirname(dirname(__DIR__))).'/vendor/autoload.php';
        $config = new Config();
        //必填,请参考"开发准备"获取如下数据,替换为实际值
        $url = $config -> getSmsURL(); //APP接入地址+接口访问URI
        $APP_KEY = $config -> getSmsAppKey(); //APP_KEY
        $APP_SECRET = $config -> getSmsAppSecret(); //APP_Secret
        $sender = $config -> getSmsSignatureChannel(); //国内短信签名通道号或国际/港澳台短信通道号
        /*
         * 您的验证码为：${1}，您正在进行用户注册，请妥善保管账户信息。
         * 为保证您的账户安全，请勿将此验证码泄露他人
         * 华为云短信-Writeoff
         *
         * */
        if($event == 'regist'){
            // regist_template注册  使用注册的模板ID
            $TEMPLATE_ID = $config -> getSmsRegistTemplate(); //模板ID

        }else if($event == 'login'){
            // login_template 登录  使用登录的模板ID
            $TEMPLATE_ID = $config -> getSmsLoginTemplate(); //模板ID

        }else if($event == 'updata_phone'){
            // updata_phone_template 修改手机号  使用修改手机号的模板ID
            $TEMPLATE_ID = $config -> getSmsUpdataPhoneTemplate(); //模板ID

        }else if($event == 'update_pwd'){
            // update_pwd_template 重置密码  使用重置密码的模板ID
            $TEMPLATE_ID = $config -> getSmsUpdatePwdTemplate(); //模板ID

        }else if($event == 'update_pay_pwd'){
            // update_pay_pwd_template 重置支付密码  使用重置支付密码的模板ID
            $TEMPLATE_ID = $config -> getSmsUpdatePayPwdTemplate(); //模板ID

        }else{
            // 通用模板ID
            $TEMPLATE_ID = $config -> getSmsGlobalTemplate(); //模板ID
        }
        //条件必填,国内短信关注,当templateId指定的模板类型为通用模板时生效且必填,必须是已审核通过的,与模板类型一致的签名名称
        //国际/港澳台短信不用关注该参数
        $signature = ""; //签名名称

        //必填,全局号码格式(包含国家码),示例:+86151****6789,多个号码之间用英文逗号分隔
        $receiver = $receiver; //短信接收人号码

        //选填,短信状态报告接收地址,推荐使用域名,为空或者不填表示不接收状态报告
        $statusCallback = '';

        /**
         * 选填,使用无变量模板时请赋空值 $TEMPLATE_PARAS = '';
         * 单变量模板示例:模板内容为"您的验证码是${1}"时,$TEMPLATE_PARAS可填写为 '["369751"]'
         * 双变量模板示例:模板内容为"您有${1}件快递请到${2}领取"时,$TEMPLATE_PARAS可填写为'["3","人民公园正门"]'
         * 模板中的每个变量都必须赋值，且取值不能为空
         * 查看更多模板格式规范:产品介绍>模板和变量规范
         * @var string $TEMPLATE_PARAS
         */
        $TEMPLATE_PARAS = '["'.$code.'"]'; //模板变量，此处以单变量验证码短信为例，请客户自行生成6位验证码，并定义为字符串类型，以杜绝首位0丢失的问题（例如：002569变成了2569）。

        $client = new Client();

        try {
            $response = $client->request('POST', $url, [
                'form_params' => [
                    'from' => $sender,
                    'to' => $receiver,
                    'templateId' => $TEMPLATE_ID,
                    'templateParas' => $TEMPLATE_PARAS,
                    'statusCallback' => $statusCallback,
                    'signature' => $signature //使用国内短信通用模板时,必须填写签名名称
                ],
                'headers' => [
                    'Authorization' => 'WSSE realm="SDP",profile="UsernameToken",type="Appkey"',
                    'X-WSSE' => Sms::buildWsseHeader($APP_KEY, $APP_SECRET)
                ],
                'verify' => false //为防止因HTTPS证书认证失败造成API调用失败，需要先忽略证书信任问题
            ]);
            ;
           $res= json_decode($response->getBody()->getContents(),true);
            if ($res['code'] == '000000'){
                return true;

            }else{
                Log::error($response);
                return false;
            }
        } catch (RequestException $e) {
            return false;
        }
    }

    /**
     * 构造X-WSSE参数值
     * @param string $appKey
     * @param string $appSecret
     * @return string
     */
    public static function buildWsseHeader(string $appKey, string $appSecret){
        $now = date('Y-m-d\TH:i:s\Z'); //Created
        $nonce = uniqid(); //Nonce
        $base64 = base64_encode(hash('sha256', ($nonce . $now . $appSecret))); //PasswordDigest
        return sprintf("UsernameToken Username=\"%s\",PasswordDigest=\"%s\",Nonce=\"%s\",Created=\"%s\"",
            $appKey, $base64, $nonce, $now);
    }
}
