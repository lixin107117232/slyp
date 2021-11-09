<?php

namespace app\api\controller;

use app\admin\controller\general\Config;
use app\common\controller\Api;
use app\common\library\Sms as Smslib;
use app\common\model\User;
use think\Hook;
use think\Log;


/**
 * 手机短信接口
 */
class Sms extends Api
{
    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';

    /**
     * 发送验证码
     *
     * @param string $mobile 手机号
     * @param string $event 事件名称:regist注册,login登录,updata_phone修改手机号,update_pwd重置登录密码,update_pay_pwd重置支付密码,add_back新增银行卡
     * @ApiReturn   ({
        'code':'1成功 0失败',
        'msg':''
        'data' :{
            "code": "验证码"
            "event": "事件名称:regist注册,login登录,updata_phone修改手机号,update_pwd重置登录密码,update_pay_pwd重置支付密码,add_back新增银行卡"
            }
        })
     */
    public function send()
    {
        $mobile = input("mobile");
        $event = input("event");

        // 判断用户是否在个人中心重置密码
        if($event == 'update_pwd'){
            if(strlen($mobile) != 11){
                // 输入token
                // 执行登录，获取用户手机号
                $this->auth->init($mobile);
                $mobile = $this -> auth -> getUser()['mobile'];
            }
        }
        if($event != 'update_pay_pwd'){
            if(!$mobile){
                $this->error(__('请输入手机号'),'mobile');
            }
            if (!\think\Validate::regex($mobile, "/^1(3[0-9]|4[01456879]|5[0-35-9]|6[2567]|7[0-8]|8[0-9]|9[0-35-9])\d{8}$/")) {
                $this->error(__('手机号不正确'));
            }
        }else{
            // 执行登录，获取用户手机号
            $this->auth->init($mobile);
            $mobile = $this -> auth -> getUser()['mobile'];
        }
        if($event != 'regist' && $event != 'login' && $event != 'updata_phone' && $event != 'update_pwd' && $event != 'update_pay_pwd' && $event != 'add_back'){
            $this->error(__('提交的事件类型不正确'));
        }
        $last = Smslib::get($mobile, $event);
        if ($last && time() - $last['createtime'] < 60) {
            $this->error(__('发送频繁,请稍后重试'));
        }
        $ipSendTotal = \app\common\model\Sms::where(['ip' => $this->request->ip()])
            ->whereTime('createtime', '-1 hours')
            ->count();
        if ($ipSendTotal >= 5) {
            $this->error(__('发送频繁,请稍等一会'),$ipSendTotal);
        }
        // 如果事件类型为：用户注册，则判断该号码是否存在
        if($event == 'regist'){
            $userinfo = User::getByMobile($mobile);
            if ($event == 'regist' && $userinfo) {
                //已被注册
                $this->error(__('该手机号已被注册'));
            } elseif (in_array($event, ['changemobile']) && $userinfo) {
                //被占用
                $this->error(__('该手机号已被占用'));
            } elseif (in_array($event, ['changepwd', 'resetpwd']) && !$userinfo) {
                //未注册
                $this->error(__('未注册'));
            }
        }
        $ret = Smslib::send($mobile, null, $event);
        if ($ret) {
            $this->success(__('发送成功'),['code'=>$ret,'event'=>$event]);
        } else {
            $this->error(__('发送失败，请检查短信配置是否正确'),$ret);
        }
    }

    /**
     * 检测验证码
     *
     * @param string $mobile 手机号
     * @param string $event 事件名称:regist注册,login登录,updata_phone修改手机号,update_pwd重置密码,update_pay_pwd重置支付密码"
     * @param string $captcha 验证码
     */
    public function check()
    {
        $mobile = input("mobile");
        $event = input("event");
        $captcha = input("captcha");

        if (!$mobile || !\think\Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('手机号不正确'));
        }
        $ret = Smslib::check($mobile, $captcha, $event);
        if ($ret) {
            $this->success(__('验证码存在'));
        } else {
            $this->error(__('验证码不正确'));
        }
    }

    public function huawei()
    {
        $config = new \app\common\model\Config();
        return $config -> getHospitalXiaoDaiZong();
        $code = mt_rand(1000, 9999);
        $ret = Smslib::huawei('18398097325',$code,'regist');
        dump($ret);die;
    }
}
