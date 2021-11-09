<?php

namespace app\api\controller;

use app\admin\model\Moneyexamine;
use app\admin\model\UserAchievement;
use app\common\controller\Api;
//use app\common\library\Log;
use app\common\library\Sms;
use app\common\model\Config;
use app\common\model\UserAddress;
use app\common\model\UserBack;
use app\common\model\UserPaymentMethod;
use fast\Random;
use think\Validate;
use app\api\BdOCR;
use app\common\library\Upload;
use think\Log;


/**
 * 会员接口
 */
class User extends Api
{
    protected $noNeedLogin = [
        'login',
        'mobilelogin',
        'register',
        'resetpwd',
        'third',
        'invitation_rigister',
        'about_us',
        'back_list',
        'getUserConfig'
    ];
    protected $noNeedRight = '*';

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 会员中心
     * @param string $token  用户唯一标识
     * @ApiReturn   ({
        'code':'1查询成功 401未登录',
        'msg':''
        'data' :{
            "id": '用户ID',
            "username": "用户名",
            "nickname": "昵称",
            "mobile": "手机号",
            "avatar": "头像",
            "bj_img": "背景图",
            "level": "等级：1消费者 2代理商 3总代理",
            "money": "余额",
            "score": "积分",
            "bonus": "奖金",
          }
     })
     */
    public function index()
    {
        // 获取用户数据
        $ret = $this->auth->getUserinfo();
        $this->success('ok', $ret);
//        $this->success('', ['welcome' => $this->auth->nickname]);
    }

    /**
     * 会员登录
     *
     * @param string $account  账号
     * @param string $password 密码
     * @ApiReturn   ({
        'code':'1登录成功 0密码、账户不正确',
        'msg':''
        'data' :{
            "id": '用户ID',
            "token": "用户唯一标识"
        })
     }
     */
    public function login()
    {

        $account = input('account');
        $password = input('password');
        if(empty($account)){
            $this->error("Mobile can not be empty");
        }
        if (!$password) {
            $this->error("Password can not be empty");
        }
        $ret = $this->auth->login($account, $password);
        if ($ret) {
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 手机验证码登录
     *
     * @param string $mobile  手机号
     * @param string $captcha 验证码
     */
    public function mobilelogin()
    {
        $mobile = input('mobile');
        $captcha = input('captcha');
        if (!$mobile) {
            $this->error(__('Mobile can not be empty'));
        }
      /*  if (!$captcha) {
            $this->error(__('Captcha can not be empty'));
        }*/
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Phone format is incorrect'));
        }
       if (!Sms::check($mobile, $captcha, 'login')) {
            $this->error(__('Captcha is incorrect'));
        }
        $user = \app\common\model\User::getByMobile($mobile);
        if ($user) {
            if ($user->status != 'normal') {
                $this->error(__('Account is locked'));
            }
            //如果已经有账号则直接登录
            $ret = $this->auth->direct($user->id);
        } else {
            // 手机号不存在
            $this->error(__('Mobile does not exist'));
        }
        if ($ret) {
            Sms::flush($mobile, 'login');
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Logged in successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }

    /**
     * 注册会员
     *
     * @param string $mobile   手机号
     * @param string $code   验证码
     * @param string $password 密码
     * @param string $password_again 重复密码
     * @param string $agreement 协议是否勾选：1勾选,0未勾选
     * @ApiReturn   ({
            'code':'1成功 0失败',
            'msg':''
            'data' :{
                "id": '用户ID',
                "token": "用户唯一标识"
            }
        })
     */
    public function register()
    {
        $mobile = input('mobile');
        $code = input('code');
        $password = input('password');
        $password_again = input('password_again');
        $agreement = input('agreement');

        $this->error("请扫描推荐人二维码注册！");

        if($agreement != 1){
            $this->error(__('Please check registration agreement'));
        }
        // 验证电话
        if (!$mobile) {
            $this->error(__('Mobile can not be empty'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Phone format is incorrect'));
        }
        // 验证密码
        if (!$password) {
            $this->error(__('Password can not be empty'));
        }
        if (!$password_again) {
            $this->error(__('Enter password again'));
        }
        if($password != $password_again){
            $this->error(__('Twice input password incorrect'));
        }
        if (\app\common\model\User::where('mobile', $mobile)->field('id')->find()) {
            $this->error(__('Mobile already exist'));
        }
        // 判断验证码
        if (!$code) {
            $this->error(__('Captcha can not be empty'));
        }
        $i = Sms::check($mobile, $code, 'regist');
        if (!$i) {
            $this->error(__('Sorry, your verification code is invalid'));
        }
        $ret = $this->auth->register($mobile, $password,[]);
        if ($ret) {
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Sign up successful'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }


    /**
     * 邀请码注册
     *
     * @param string $phone   手机号
     * @param string $code   验证码
     * @param string $password 密码
     * @param string $password_again 重复密码
     * @param string $user_code 邀请码
     * @param string $agreement 协议是否勾选：1勾选,0未勾选
     * @ApiReturn   ({
    'code':'1成功 0失败',
    'msg':''
    })
     */
    public function invitation_rigister()
    {
        $phone = input("phone");
        $code = input("code");
        $password = input("password");
        $password_again = input("password_again");
        $agreement = input("agreement");
        $user_code = input("user_code");


        if($agreement != 1){
            $this->error(__('Please check registration agreement'));
        }
        // 验证电话
        if (!$phone) {
            $this->error(__('Mobile can not be empty'));
        }
        if (!Validate::regex($phone, "^1\d{10}$")) {
            $this->error(__('Phone format is incorrect'));
        }
        // 验证密码
        if (!$password) {
            $this->error(__('Password can not be empty'));
        }
        if (!$password_again) {
            $this->error(__('Enter password again'));
        }
        if($password != $password_again){
            $this->error(__('Twice input password incorrect'));
        }
        $user_mobile = \app\common\model\User::where('mobile', $phone)
            ->field('id')
            ->find();
        if ($user_mobile) {
            $this->error(__('Mobile already exist'));
        }
        if(!$user_code){
            $this->error("无邀请人，注册失败");
        }
        $user_id = \app\common\model\User::where('my_invitation_code', $user_code)
            ->field('id')
            ->find();
        if(!$user_id){
            $this->error(__('Invitation user does not exist'));
        }
        // 判断验证码
        /*if (!$code) {
            $this->error(__('Captcha can not be empty'));
        }
        $ret = Sms::check($phone, $code, 'regist');
        if (!$ret) {
            $this->error(__('Captcha is incorrect'));
        }*/
        $ret = $this->auth->invitation_register($phone, $password,$user_id->id,[]);
        if ($ret) {
            $data = ['userinfo' => $this->auth->getUserinfo()];
            $this->success(__('Sign up successful'), $data);
        } else {
            $this->error("注册失败，当前网络不稳定，请重试！");
        }
    }


    /**
     * 重置密码
     *
     * @param string $mobile      手机号
     * @param string $newpassword 新密码
     * @param string $newpassword_again 重复新密码
     * @param string $captcha     验证码
     * @ApiReturn   ({
    'code':'1成功 0失败',
    'msg':''
    })
     */
    public function resetpwd()
    {
        $mobile = input("mobile");
        $newpassword = input("newpassword");
        $newpassword_again = input("newpassword_again");
        $captcha = input("captcha");

        if (!$mobile) {
            $this->error(__('请输入重置手机号'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Phone format is incorrect'));
        }
        // 判断密码两次输入的密码是否相同
        if (!$newpassword) {
            $this->error(__('Enter new password'));
        }
        if (!$newpassword_again) {
            $this->error(__('Enter repeat new password'));
        }
        if ($newpassword != $newpassword_again) {
            $this->error(__('Twice input password incorrect'));
        }
        // 验证该用户是否存在
        $user = \app\common\model\User::getByMobile($mobile);
        if (!$user) {
            $this->error(__('Account not exist'));
        }
        // 校正短信验证码
       if (!$captcha) {
            $this->error(__('Captcha can not be empty'));
        }
        $ret = Sms::check($mobile, $captcha, 'update_pwd');
        if (!$ret) {
            $this->error(__('Captcha is incorrect'));
        }
        Sms::flush($mobile, 'update_pwd');
        //模拟一次登录
        $this->auth->direct($user->id);
        $ret = $this->auth->changepwd($newpassword, '', true);
        if ($ret) {
            $this->success(__('Reset password successful'));
        } else {
            $this->error($this->auth->getError());
        }
    }


    /**
     * 个人中心-重置密码
     * @param string $token 用户唯一标识
     * @param string $oldpassword 旧密码
     * @param string $newpassword 新密码
     * @param string $newpassword_again 重复新密码
     * @param string $captcha     验证码
     * @ApiReturn   ({
        'code':'1成功 0失败',
        'msg':''
     })
    */
    public function home_resetpwd(){
        $oldpassword = input("oldpassword");
        $newpassword = input("newpassword");
        $newpassword_again = input("newpassword_again");
        $captcha = input("captcha");

        // 获取用户数据
        $user = $this -> auth -> getUser();

        if (!$oldpassword) {
            $this->error(__('Enter old password'));
        }
        // 判断密码两次输入的密码是否相同
        if (!$newpassword) {
            $this->error(__('Enter new password'));
        }
        if (!$newpassword_again) {
            $this->error(__('Enter repeat new password'));
        }
        if ($newpassword != $newpassword_again) {
            $this->error(__('Twice input password incorrect'));
        }
        // 校正旧密码
        if(md5(md5($oldpassword) . $user['salt']) != $user['password']){
            $this->error(__('Old password is incorrect'));
        }
        // 校正短信验证码
        if (!$captcha) {
            $this->error(__('Captcha can not be empty'));
        }

        $ret = Sms::check($user['mobile'], $captcha, 'update_pwd');
        if (!$ret) {
            $this->error(__('Captcha is incorrect'));
        }
        Sms::flush($user['mobile'], 'update_pwd');
        //模拟一次登录
        $this->auth->direct($user['id']);
        $ret = $this->auth->changepwd($newpassword, '', true);
        if ($ret) {
            $this->success(__('Reset password successful'));
        } else {
            $this->error($this->auth->getError());
        }
    }


    /**
     * 退出登录
     * @param string $token   用户唯一标识
     */
    public function logout()
    {
        $this->auth->logout();
        $this->success(__('Logout successful'));
    }

    /**
     * 修改会员个人信息
     *
     * @param string $token   用户唯一标识
     * @param string $avatar   头像地址
     * @param string $username 用户名
     * @param string $nickname 昵称
     * @param string $bio      个人简介
     */
    private function profile()
    {
        $user = $this->auth->getUser();
        $username = input('username');
        $nickname = input('nickname');
        $bio = input('bio');
        $avatar = input('avatar', '', 'trim,strip_tags,htmlspecialchars');
        if ($username) {
            $exists = \app\common\model\User::where('username', $username)
                ->where('id', '<>', $this->auth->id)
                ->find();
            if ($exists) {
                $this->error(__('Username already exists'));
            }
            $user->username = $username;
        }
        if ($nickname) {
            $exists = \app\common\model\User::where('nickname', $nickname)
                ->where('id', '<>', $this->auth->id)
                ->find();
            if ($exists) {
                $this->error(__('Nickname already exists'));
            }
            $user->nickname = $nickname;
        }
        $user->bio = $bio;
        $user->avatar = $avatar;
        $user->save();
        $this->success();
    }

    /**
     * 修改头像
     *
     * @param string $token   用户唯一标识
     * @param string $url   修改路径
     * @ApiReturn   ({
    'code':'1成功 0失败 401未登录',
    'msg':''
    })
     */
    public function updavatar()
    {
        $user = $this->auth->getUser();
        $url = input('url');
        if (!$url) {
            $this->error(__('Please select the picture you want to modify'));
        }
        // 修改头像
        $user->avatar = $url;
        $user->save();

        $this->success("修改成功");
    }

    /**
     * 修改背景图
     *
     * @param string $token   用户唯一标识
     * @param string $url   修改路径
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':''
        })
     */
    public function upd_bj_img()
    {
        $user = $this->auth->getUser();
        $url = input('url');
        if (!$url) {
            $this->error(__('Please select the picture you want to modify'));
        }
        // 修改背景
        $user->bj_img = $url;
        $user->save();

        $this->success("Modified successfully");
    }

    /**
     * 修改昵称
     *
     * @param string $token   用户唯一标识
     * @param string $newnickname   新昵称
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':''
        })
     */
    public function updnickname()
    {
        $user = $this->auth->getUser();
        $newnickname = input('newnickname');
        if (!$newnickname) {
            $this->error(__('Please enter a new nickname'));
        }
        // 修改昵称
        $user->nickname = $newnickname;
        $user->save();

        $this->success();
    }

    /**
     * 修改手机号
     *
     * @param string $token   用户唯一标识
     * @param string $mobile   手机号
     * @param string $captcha 验证码
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':''
        })
     */
    public function changemobile()
    {
        $user = $this->auth->getUser();
        $mobile = input('mobile');
        $captcha = input('captcha');
       /* if (!$captcha) {
            $this->error(__('Captcha can not be empty'));
        }
        */
        // 验证手机号
        if (!$mobile) {
            $this->error(__('Mobile can not be empty'));
        }
        if (!Validate::regex($mobile, "^1\d{10}$")) {
            $this->error(__('Phone format is incorrect'));
        }
        if (\app\common\model\User::where('mobile', $mobile)->where('id', '<>', $user->id)->find()) {
            $this->error(__('Mobile already exist'));
        }
        // 验证-验证码
       /* $result = Sms::check($mobile, $captcha, 'updata_phone');
        if (!$result) {
            $this->error(__('Captcha is incorrect'));
        }*/
        // 保存手机号
        $user->mobile = $mobile;
        $user->token = "";
        $user->save();
        // 退出登录
        $this->auth->logout();

        Sms::flush($mobile, 'updata_phone');
        $this->success();
    }

    /**
     * 修改支付密码
     *
     * @param string $token   用户唯一标识
     * @param string $newpaypwd   新支付密码
     * @param string $newpaypwd_again   确认密码
     * @param string $captcha 验证码
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':''
        })
     */
    public function updpaypwd()
    {
        $user = $this->auth->getUser();
        $newpaypwd = input("newpaypwd");
        $newpaypwd_again = input("newpaypwd_again");
        $captcha = input("captcha");

        // 判断密码两次输入的密码是否相同
        if (!$newpaypwd) {
            $this->error(__('Enter new password'));
        }
        if (!$newpaypwd_again) {
            $this->error(__('Enter repeat new password'));
        }
        if ($newpaypwd != $newpaypwd_again) {
            $this->error(__('Twice input password incorrect'));
        }
       /* if (!$captcha) {
            $this->error(__('Captcha can not be empty'));
        }
        // 校正短信验证码
        $ret = Sms::check($user->mobile, $captcha, 'update_pay_pwd');
        if (!$ret) {
            $this->error(__('Captcha is incorrect'));
        }
        Sms::flush($user->mobile, 'update_pay_pwd');*/
        //模拟一次登录
        $this->auth->direct($user->id);
        $ret = $this->auth->changepaypwd($newpaypwd, '', true);
        if ($ret) {
            $this->success(__('Reset password successful'));
        } else {
            $this->error($this->auth->getError());
        }
    }
    /*
     * 获取默认地址
     * */
    public function getaddress(){
        // 获取用户地址数据
        $user = $this->auth->getUser();

        $address=UserAddress::where(["user_id"=>$user["id"],"is_del"=>0])
            -> order('type','desc')
            -> order('createtime','desc')
            -> find();

        $this->success('', $address);
    }


    /**
     * 地址管理
     *
     * @param string $token   用户唯一标识
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':'',
        'data' :{
            "id": "地址ID",
            "user_id": "用户ID",
            "name": "收件人",
            "phone": "联系电话",
            "province_city_area": "省市区",
            "address": "详细地址",
            "type": "1默认 0未默认",
        }
     })
     */
    public function address_manage()
    {
        // 获取用户地址数据
        $ret = $this->auth->getAddress();
        if($ret){
            $this->success('Query was successful', $ret);
        }else{
            $this->error(__('Query no data'));
        }

    }


    /**
     * 新增收获地址
     *
     * @param string $token   用户唯一标识
     * @param string $name   收件人
     * @param string $phone   联系电话
     * @param string $province_city_area   省市区
     * @param string $address   详细地址
     * @param string $type   是否默认：1默认 0未默认
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':'',
        "data": {
            "useraddress": {
                {
                    "id": "地址ID",
                    "user_id": "用户ID",
                    "name": "收件人",
                    "phone": "联系电话",
                    "province_city_area": "省市区",
                    "address": "详细地址",
                    "type": "1默认 0未默认",
                }
            }
         }
     })
     */
    public function add_address()
    {
        // 获取提交数据
        $name = input("name");
        $phone = input("phone");
        $province_city_area = input("province_city_area");
        $address = input("address");
        $type = input("type");

        // 验证
        if(!$name){
            $this->error(__('Please enter the recipient'));
        }
        if(!$phone){
            $this->error(__('Please enter the contact number'));
        }
        if (!Validate::regex($phone, "^1\d{10}$")) {
            $this->error(__('Phone format is incorrect'));
        }
        if(!$province_city_area){
            $this->error(__('Please select province or city'));
        }
        if(!$address){
            $this->error(__('Please enter the detailed address'));
        }
        $i = $this -> auth -> addAddress($name,$phone,$province_city_area,$address,$type);
        if ($i) {
            $data = ['useraddress' => $this->auth->getAddress()];
            $this->success(__('Address added successfully'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }


    /**
     * 编辑收获地址
     *
     * @param string $token   用户唯一标识
     * @param string $address_id   收获地址ID
     * @param string $name   收件人
     * @param string $phone   联系电话
     * @param string $province_city_area   省市区
     * @param string $address   详细地址
     * @param string $type   是否默认：1默认 0未默认
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':'',
        "data": {
            "useraddress": {
                {
                    "id": "地址ID",
                    "user_id": "用户ID",
                    "name": "收件人",
                    "phone": "联系电话",
                    "province_city_area": "省市区",
                    "address": "详细地址",
                    "type": "1默认 0未默认",
                }
            }
        }
     })
     */
    public function upd_address()
    {
        // 获取提交数据
        $address_id = input("address_id");
        $name = input("name");
        $phone = input("phone");
        $province_city_area = input("province_city_area");
        $address = input("address");
        $type = input("type");

        // 验证
        if(!$address_id){
            $this->error(__('Please submit the harvest address ID'));
        }
        if(!$name){
            $this->error(__('Please enter the recipient'));
        }
        if(!$phone){
            $this->error(__('Please enter the contact number'));
        }
        if (!Validate::regex($phone, "^1\d{10}$")) {
            $this->error(__('Phone format is incorrect'));
        }
        if(!$province_city_area){
            $this->error(__('Please select province or city'));
        }
        if(!$address){
            $this->error(__('Please enter the detailed address'));
        }
        $i = $this -> auth -> updAddress($address_id,$name,$phone,$province_city_area,$address,$type);

        if ($i) {
            $data = ['useraddress' => $this->auth->getAddress()];
            $this->success(__('Address modified successfully'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }


    /**
     * 删除收获地址
     *
     * @param string $token   用户唯一标识
     * @param string $address_id   收获地址ID
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':'',
        "data": {
            "useraddress": {
                {
                    "id": "地址ID",
                    "user_id": "用户ID",
                    "name": "收件人",
                    "phone": "联系电话",
                    "province_city_area": "省市区",
                    "address": "详细地址",
                    "type": "1默认 0未默认",
                }
            }
        }
     })
     */
    public function del_address()
    {
        // 获取提交数据
        $address_id = input("address_id");

        // 验证
        if(!$address_id){
            $this->error(__('Please submit the harvest address ID to be deleted'));
        }
        $i = $this -> auth -> delAddress($address_id);

        if ($i) {
            $data = ['useraddress' => $this->auth->getAddress()];
            $this->success(__('Address deleted successfully'), $data);
        } else {
            $this->error($this->auth->getError());
        }
    }


    /**
     * 查询收款方式
     *
     * @param string $token   用户唯一标识
     * @param string $type   收款类型：1微信,2支付宝
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':'',
        "data": {
            {
                "id": "收款方式ID",
                "user_id": "用户ID",
                "type": "收款类型：1微信，2支付宝",
                "name": "姓名",
                "phone": "联系电话",
                "url": "图片地址",
            }
        }
     })
     */
    public function payment_method()
    {
        // 获取提交数据
        $type = input("type");

        // 验证
        if(!$type){
            $this->error(__('Please submit the collection type to be queried'));
        }
        // 获取用户收款数据
        $ret = $this->auth->getPaymentMethod($type);
        if($ret){
            $this->success('Query was successful', $ret);
        }else{
            $this->error(__('Query no data'));
        }
    }


    /**
     * 添加收款方式
     *
     * @param string $token   用户唯一标识
     * @param string $type   收款类型：1微信,2支付宝
     * @param string $name   姓名
     * @param string $phone   账号
     * @param string $url   二维码相对路径
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':''
     })
     */
    public function add_payment_method()
    {
        // 获取提交数据
        $type = input("type");
        $name = input("name");
        $phone = input("phone");
        $url = input("url");

        // 验证
        if(!$type){
            $this->error(__('Please submit the collection type to be queried'));
        }
        if(!$name){
            $this->error(__('Please enter your name'));
        }
        if($type == 2){
            if(!$phone){
                $this->error(__('Please enter the account number'));
            }
        }
        if($type == 1){
            $phone = "";
        }

        if(!$url){
            $this->error(__('Please submit the QR code path'));
        }
        // 获取用户收款数据
        $ret = $this->auth->addPaymentMethod($type,$name,$url,$phone);
        if($ret){
            $this->success(__("Added successfully"), $ret);
        }else{
            $this->error(__('Added failed'));
        }
    }


    /**
     * 删除收款方式
     *
     * @param string $token   用户唯一标识
     * @param string $payment_method_id   收款方式ID
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':''
        })
     */
    public function del_payment_method()
    {
        // 获取提交数据
        $payment_method_id = input("payment_method_id");

        // 验证
        if(!$payment_method_id){
            $this->error(__('Please submit the collection method id to be deleted'));
        }
        $i = $this -> auth -> delPaymentMethod($payment_method_id);

        if ($i) {
            $this->success('Deleted successfully', $i);
        } else {
            $this->error($this->auth->getError());
        }
    }


    /**
     * 实名认证
     * @param string $token  用户唯一标识
     * @ApiReturn   ({
        'code':'1查询成功 401未登录',
        'msg':'',
        "data": {
            "real_status": "实名状态：1已实名 0未实名",
            "real_name": "真实姓名",
            "id_card": "身份证号码",
            "id_card_zm": "身份证正面",
            "id_card_fm": "身份证反面"
        }
     })
     */
    public function identity_verification()
    {
        // 获取用户数据
        $ret = $this->auth->getIdentityVerification();
        $this->success('ok', $ret);
    }


    /**
     * 保存实名认证
     * @param string $token  用户唯一标识
     * @param string $real_name  真实姓名
     * @param string $id_card  身份证号码
     * @param string $id_card_zm  身份证正面
     * @param string $id_card_fm  身份证反面
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':'',
        "data": {
            "user_identity_verification": {
                "real_status": "实名状态：1已实名 0未实名",
                "real_name": "真实姓名",
                "id_card": "身份证号码",
                "id_card_zm": "身份证正面相对路径",
                "id_card_fm": "身份证反面相对路径"
            }
        }
     })
     */
    public function save_identity_verification()
    {

        // 获取提交数据
        $real_name = input("real_name");
        $id_card = input("id_card");
        $id_card_zm = input("id_card_zm");
        $id_card_fm = input("id_card_fm");

        if(!$id_card_zm){
            $this->error(__('Front of ID card'));
        }
        if(!$id_card_fm){
            $this->error(__('Reverse side of ID card'));
        }
        $i = $this -> auth -> saveIdentityVerification($real_name,$id_card,$id_card_zm,$id_card_fm);

        if ($i){




        }
        if ($i) {
            $data = ['user_identity_verification' => $this->auth->getIdentityVerification()];
            $this->success(__('Real name success'), $data);
        } else {
            $this->error($this->auth->getError());
        }




    }


    /**
     * 邀请好友
     * @param string $token  用户唯一标识
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':'',
        "data": "二维码路径"
     })
     */
    public function user_qrcode()
    {
        $user = $this -> auth -> getUser();
        // 判断用户是否已经生成邀请码
        $my_invitation_code = $user->my_invitation_code;
        if(!$my_invitation_code){
            // 生成邀请码
            $code = Random::numericAndAllCapital(8);
            $user -> my_invitation_code = $code;
            $user -> save();
        }

        // 用户邀请码
        $user_code = $user -> my_invitation_code;

        $value = 'http://'.$_SERVER['HTTP_HOST']."/index/user/registers.html?user_code=".$user_code;         //二维码路径

        $code_url['code_url'] = $this -> auth -> qrcode($value,$user_code);
        $code_url['my_invitation_code'] = $my_invitation_code;
        return $this->success("ok",$code_url);
    }


    /**
     * 我的团队-我的直推
     * @param int $page 页数,默认为:1,每页5条数据
     * @param string $token   用户唯一标识
     * @ApiReturn   ({
        'code':'1成功 0失败',
        'msg':''
        'data' :{
            "zt_num": "直推人数",
            "team_num": "团队人数",
            "user_data":{
                {
                    "id": "会员ID",
                    "fictitious_id": "虚拟id",
                    "nickname": "会员昵称",
                    "createtime": ”会员创建时间“,
                    "level": "等级：1消费者 2代理商 3总代理",
                    "mobile": "会员电话",
                    "avatar": "会员头像",
                }
            }
        }
     })
     */
    public function my_team($id)
    {
        // 获取用户团队数据
        $page = input('page');
        if(!$page){
            $page = 1;
        }
        $ret = $this->auth->getUserTeam($page,$id);
        $this->success('ok', $ret);
    }

    /**
     * 我的余额
     *
     * @param string $token   用户唯一标识
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':'',
        "data": {
            "my_money": "当前余额",
            "min_money_cash": "最低金额提现",
            "max_money_cash": "最高金额提现,0为无限制",
            "cash_money_number": "每天允许提现次数",
            "money_cash_charge": "手续费%",
        }
     })
     */
    public function my_money()
    {
        $ret = $this->auth->getMyMoney();
        $this->success('ok', $ret);
    }

    /**
     * 余额详情
     *
     * @param string $token   用户唯一标识
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':'',
        "data": {
            {
                "id": "详情ID",
                "fictitious_id": "虚拟id",
                "user_id": "用户ID",
                "money": "变更金额",
                "before": "变更前金额",
                "after": "变更后金额",
                "memo": "备注",
                "createtime": "创建时间"
            }
        }
     })
     */
    public function money_log()
    {
        $ret = $this->auth->getMoneyLog();
        $this->success('ok', $ret);
    }


    /**
     * 查询余额转账记录
     * @param string $token 用户唯一标识
     * @ApiReturn   ({
        'code':'1成功 0失败  401未登录',
        'msg':'',
        'data':{
            {
                "id": "记录ID",
                "user_id": "用户ID",
                "score": "提现金额",
                "before": "提现前金额",
                "after": "提现后金额",
                "memo": "备注",
                "status": "状态：1审核中，2审核成功，3审核失败",
                "createtime": "创建时间"
            }
        }
    })
     */
    public function select_money_log(){
        $data = $this -> auth -> SelectMoneyLog();
        $this -> success(__("Query was successful"),$data);
    }

    /**
     * 余额提现申请
     * @param string $token 用户唯一标识
     * @param int $num 转账金额
     * @param string $pay_method 提现方式：1微信2支付宝3银行卡
     * @param int $id 收款码/银行卡id
     *
     */
    public function do_money(){
        $num = input("num");
        $pay_method = input("pay_method");
        $id = input("id");
        $user = $this -> auth -> getUser();
        // 获取余额最低提现
        $money_config = Config::get(["id"=>26])['value'];
        // 最多体现金额
        $max_money_cash = Config::get(["id"=>28])['value'];
        // 每天允许提现次数
        $cash_money_number = Config::get(["id"=>27])['value'];
        // 手续费
        $money_cash_charge = Config::get(["id"=>31])['value'];


        // 验证
        if(!$num){
            $this->error(__('Please input transfer quantity'));
        }
        if(!is_numeric($num)||strpos($num,".")!==false){
            $this->error(__('Transfer integral must be an integer'));
        }
        // 是否低于最低提现
        if($num < $money_config){
            $this->error(__('No less than the minimum withdrawal'));
        }
        // 提现不能超过当日限额
        if($num > $max_money_cash){
            $this->error(__('Not higher than the minimum withdrawal'));
        }
        $system_money = \app\common\model\Config::get(["id"=>88]);
        $now_money    =     \app\common\model\Config::get(["id"=>89]);
        if ($now_money->value > $system_money->value || ($num+$now_money->value) > $system_money->value ){
            $this->error('提现失败,今日提现总额度已达上限');
        }
        // 判断余额
        if($num > $user['money']){
            $this->error("当前余额不足");
        }
        // 获取用户今天提现次数
        $beginToday=mktime(0,0,0,date('m'),date('d'),date('Y'));
        $endToday=mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;
        $w['createtime'] = array('between', array($beginToday,$endToday));
        $withdrawallog_data = Moneyexamine::where('user_id',$user['id'])
            -> where($w)
            -> select();
        // 不能超过每天提现次数
        if(count($withdrawallog_data) >= $cash_money_number){
            $this->error(__('No more than the number of withdrawals per day'));
        }

        // 如果用户选择银行卡提现
        if($pay_method == 3){
            // 查询上传的银行卡id
            $back_data = UserBack::where(['user_id' => $user['id'],'id' => $id,'deletetime' => null]) -> find();

            if(!$back_data){
                $this -> error("未查询到该银行卡，请重新选择");
            }
            $method_image = $back_data['back_no'];
        }else{

            // 判断用户是否添加微信或者银行卡收款码
            $shoukuan = UserPaymentMethod::where(['user_id' => $user['id'],'type' => $pay_method,'id' => $id]) -> find();

            if(!$shoukuan){
                $this -> error("未查询到该收款码，请重新选择");
            }

            $method_image = $shoukuan['url'];
        }



        // 扣除手续费之后实际提现金额
        $new_money = $num - ($num * $money_cash_charge/100);

        $data = [
            'user_id' => $user['id'],
            'old_score' => $num,
            'score' => $new_money,
            'memo' => "提现",
            'status' => 1,
            'pay_method' => $pay_method,
            'method_image' => $method_image,
            'method_id' => $id,
            'createtime' => time(),
        ];
        $i = Moneyexamine::insert($data);
        $this -> auth -> edit_money_log($num,"转出",'-');

        if($i){
            $this -> success("申请提交成功");
        }else{
            $this -> error("申请提交失败");
        }

    }


    /**
     * 我的积分
     *
     * @param string $token 用户唯一标识
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':'',
        "data": {
            "my_score": "当前积分",
            "min_score_cash": "最低积分划转",
            "integral_transfer_charge": "手续费%",
        }
     })
     */
    public function my_score()
    {
        $user = $this -> auth -> getUser();
        // 如果用户选择余额支付，则需要判断用户是否设置支付密码
        if(!$user['pay_pwd']){
            $pay_pwd_data = [
                "code"=> 4,
                "msg"=> "用户未设置支付密码",
                "time"=> time(),
                "data"=> null
            ];
            return \GuzzleHttp\json_encode($pay_pwd_data,JSON_UNESCAPED_UNICODE);
        }
        $ret = $this->auth->getMyScore();
        $this->success('ok', $ret);
    }


    /**
     * 积分划转
     *
     * @param string $token 用户唯一标识
     * @param string $num 划转数量
     * @param string $other_id 对方ID
     * @param string $pay_pwd 支付密码
     * @ApiReturn   ({
        'code':'1成功 0失败  3未实名 4未设置支付密码 401未登录',
        'msg':''
     })
     */
    public function do_score()
    {
        // 获取提交数据
        $num = input("num");
        $other_id = input("other_id");
        $pay_pwd = input("pay_pwd");
        $user = $this -> auth -> getUser();

        // 判断用户是否实名
        if($user -> real_status == 0){
            $real_status_data = [
                "code"=> 3,
                "msg"=> "用户未实名，即将跳转到实名页面",
                "time"=> time(),
                "data"=> null
            ];
            return \GuzzleHttp\json_encode($real_status_data,JSON_UNESCAPED_UNICODE);
        }
        // 判断用户是否设置支付密码
        if(!$user -> pay_pwd){
            $pay_pwd_data = [
                "code"=> 4,
                "msg"=> "用户未设置支付密码，即将跳转到设置支付密码",
                "time"=> time(),
                "data"=> null
            ];
            return \GuzzleHttp\json_encode($pay_pwd_data,JSON_UNESCAPED_UNICODE);
        }
        // 验证
        if(!$num){
            $this->error(__('Please input transfer quantity'));
        }
        if(!is_numeric($num)||strpos($num,".")!==false){
            $this->error(__('Transfer integral must be an integer'));
        }
        // 验证划转积分是否为指定倍数
        $beishu =  Config::get(['id'=>34]) -> value;
        if($beishu <= 0){
            $beishu = 1;
        }
        if($num%$beishu != 0){
            $this->error(__('划转数量必须是【'.$beishu.'】的倍数'));
        }
        // 最低划转
        $min_huazhuan = Config::get(['id'=>29]) -> value;
        if($num < $min_huazhuan){
            $this->error("不能低于最低划转数量，最低划转：".$min_huazhuan."，当前划转：".$min_huazhuan);
        }
        // 判断用户积分是否足够
        if($num > $user -> score){
            $this->error(__('Sorry, your account is not fully charged'));
        }
        if(!$other_id){
            $this->error(__('Please input the other party is ID'));
        }
        $other_data = \app\common\model\User::where('fictitious_id',$other_id) -> field('id')->find();
        if(empty($other_data)){
            $this->error(__('The opposite user is not found'));
        }
        if($other_id == $user ->fictitious_id){
        $this->error(__('You can it transfer money to yourself'));
    }
        // 判断支付密码
        if(!$pay_pwd){
            $this->error(__('Payment password cannot be empty'));
        }

        if($user->pay_pwd != $this -> auth -> getEncryptPassword($pay_pwd,$user->pay_salt)){
            $this->error(__('Incorrect payment password'));
        }

        $res = $this -> auth -> AddScore($num,$other_id,$pay_pwd);

        if($res){
            $this -> success(__("Transfer successful"));
        }else{
            $this->error(__('Transfer failed'));
        }
    }

    /**
     * 查询积分划转记录
     * @param string $token 用户唯一标识
     * @ApiReturn   ({
        'code':'1成功 0失败  401未登录',
        'msg':'',
        'data':{
            {
                "id": "记录ID",
                "user_id": "用户ID",
                "score": "划转金额",
                "before": "变更前金额",
                "after": "变更后金额",
                "memo": "备注",
                "type": "提现类型：1金额，2积分，3奖金",
                "createtime": "创建时间"
            }
        }
     })
    */
    public function select_score_log(){
        $data = $this -> auth -> SelectScoreLog();
        $this -> success(__("Query was successful"),$data);
    }


    /**
     * 积分详情
     *
     * @param string $token   用户唯一标识
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':'',
        "data": {
            {
                "id": "详情ID",
                "user_id": "用户ID",
                "score": "变更积分",
                "before": "变更前积分",
                "after": "变更后积分",
                "memo": "备注",
                "createtime": "创建时间"
            }
        }
    })
     */
    public function score_log()
    {
        $ret = $this->auth->getScoreLog();
        $this->success('ok', $ret);
    }


    /**
     * 我的奖金
     *
     * @param string $token 用户唯一标识
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':'',
        "data": {
            "my_bonus": "当前奖金",
            "min_bonus_cash": "最低转出奖金",
            "bonus_transfer_charge": "手续费%",
        }
     })
     */
    public function my_bonus()
    {
        $ret = $this->auth->getMyBonus();
        $this->success('ok', $ret);
    }


    /**
     * 奖金详情
     *
     * @param string $token   用户唯一标识
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':'',
        "data": {
            {
                "id": "详情ID",
                "user_id": "用户ID",
                "bonus": "变更奖金",
                "before": "变更前奖金",
                "after": "变更后奖金",
                "memo": "备注",
                "createtime": "创建时间"
            }
        }
     })
     */
    public function bonus_log()
    {
        // 获取用户奖金详情
        $ret = $this->auth->getBonusLog();
        $this->success('ok', $ret);
    }


    /**
     * 查询奖金转出记录
     * @param string $token 用户唯一标识
     * @ApiReturn   ({
        'code':'1成功 0失败  401未登录',
        'msg':'',
        'data':{
            {
                "id": "记录ID",
                "user_id": "用户ID",
                "score": "转出金额",
                "before": "转出前金额",
                "after": "转出后金额",
                "memo": "备注",
                "status": "状态：1审核中，2审核成功，3审核失败",
                "createtime": "创建时间"
            }
        }
     })
     */
    public function select_bonus_log(){
        $data = $this -> auth -> SelectBonusLog();
        $this -> success("Query was successful",$data);
    }

    /**
     * 奖金转出功能
     * @param string $token 用户唯一标识
     * @param string $num 转出金额
     * @ApiReturn   ({
        'code':'1成功 0失败 3未实名 401未登录',
        'msg':''
      })
    */
    public function do_bonus(){
        // 获取提交数据
        $num = input("num");
        $user = $this -> auth -> getUser();
        // 判断用户是否实名
        if($user -> real_status == 0){
            $real_status_data = [
                "code"=> 3,
                "msg"=> "用户未实名，即将跳转到实名页面",
                "time"=> time(),
                "data"=> null
            ];
            return \GuzzleHttp\json_encode($real_status_data,JSON_UNESCAPED_UNICODE);
        }
        // 验证
        if(!$num){
            $this->error(__('Please input transfer out amount'));
        }
        if(!is_numeric($num)||strpos($num,".")!==false){
            $this->error(__('Transfer money must be an integer'));
        }
        // 验证转出金额是否为指定倍数
        $beishu =  Config::get(['id'=>34]) -> value;
        if($beishu <= 0){
            $beishu = 1;
        }
        if($num%$beishu != 0){
            $this->error(__('转出金额必须是【'.$beishu.'】的倍数'));
        }
        // 最低转出金额
        $min_huazhuan = Config::get(['id'=>30]) -> value;
        if($num < $min_huazhuan){
            $this->error(__('The minimum transfer out amount shall not be less than'.$min_huazhuan.'Current transfer'.$num));
        }
        // 判断用户奖金是否足够
        if($num > $user -> bonus){
            $this->error(__('Sorry, your account bonus is insufficient'));
        }
        $res = $this -> auth -> AddBonus($num);
//        return $this->success("ok",$res);
        if($res){
            $this -> success(__('Submitted successfully'));
        }else{
            $this->error(__('Failed to submit'));
        }
    }




    /**
     * 我的团队-业绩明细
     *
     * @param int $page 页数,默认为:1,每页10条数据
     * @param string $token 用户唯一标识
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':'',
        "data": {
            {
                "user_sum_money": "个人总消费",
                "user_lastmonth_money": "个人上月总消费",
                "team_sum_money": "团队总消费",
                "team_lastmonth_money": "团队上月总消费",
                // 业绩明细
                "team_achievement_data": [
                    {
                        "id": "明细id",
                        "money": "金额",
                        "createtime": "时间",
                        // 用户信息
                        "userdata": {
                            "nickname": "用户昵称",
                            "fictitious_id": "虚拟id",
                        }
                    }
                ]
            }
        }
     })
     *
     */
    public function myteam_achievement($id){


        // 获取用户信息
        $user = $this -> auth -> getUser();
        !empty($id)?$uid =$id:$uid=$user['id'];

            // 用户个人本月消费
        $user_sum_money = UserAchievement::where(['pid'=> 0,"user_id"=>$uid])
            -> whereTime('createtime','month')
            -> sum('money');

        // 查询用户上个月总消费
        $user_lastmonth_money = UserAchievement::where(['pid'=> 0,"user_id"=>$uid])
            -> whereTime('createtime','last month')
            -> sum('money');
        // 查询团队总消费
        $team_sum_money = UserAchievement::where(['pid'=> $uid])
            -> whereTime('createtime','month')
            -> sum('money');
        // 查询团队上个月总消费
        $team_lastmonth_money = UserAchievement::where(['pid'=> $uid])
            -> whereTime('createtime','last month')
            -> sum('money');


        $page = input('page');
        if(!$page){
            $page = 1;
        }
        $pagesize = 5;  // 每页条数
        // 查询团队消费情况
        $team_achievement_data = UserAchievement::with(['userdata'])
            -> where(['pid'=> $uid])
            -> order("createtime",'desc')
            -> paginate($pagesize,'',['page' => $page, 'list_rows' =>$pagesize]);
        foreach ($team_achievement_data as $k => $v){
            $v->visible(['id','pid','user_id','money','createtime']);
            $v->visible(['userdata']);
            $v->getRelation('userdata')->visible(['nickname','fictitious_id']);

            $team_achievement_data[$k]['createtime'] = date("Y-m-d H:i:s",$v['createtime']);
        }

        $data = [
            'user_sum_money' => $user_sum_money,
            'user_lastmonth_money' => $user_lastmonth_money,
            'team_sum_money' => $team_sum_money,
            'team_lastmonth_money' => $team_lastmonth_money,
            'team_achievement_data' => json_decode(json_encode($team_achievement_data),true)['data'],
        ];
        $this -> success("ok",$data);

    }



    /**
     * 用户银行卡列表
     *
     * @param string $token 用户唯一标识
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':'',
        "data":[
            {
                "id": "银行卡id",
                "back_name": "银行卡名称",
                "user_name": "持卡人名称",
                "back_no": "银行卡卡号",
                "back_branch": "支行信息",
                "phone": "手机号",
                "status": "1默认0未默认",
            }
        ]
     })
     */
    public function user_back_list()
    {
        $user = $this -> auth -> getUser();
        $back_data = UserBack::where(['user_id'=>$user['id'],'deletetime' => null])
            -> order("status",'desc')
            -> order("createtime",'desc')
            -> select();

        $this -> success("查询成功",$back_data);
    }

    /**
     * 添加银行卡
     * @param string $token 用户唯一标识
     * @param string $back_name 银行卡名称
     * @param string $user_name 持卡人名称
     * @param int $back_no 银行卡卡号
     * @param string $back_branch 支行信息
     * @param string $phone 手机号
     * @param string $code 验证码
     * @param int $status 1默认0未默认
     *
     */
    public function add_back(){
        $back_name = input("back_name");
        $user_name = input("user_name");
        $back_no = input("back_no");
        $back_branch = input("back_branch");
        $phone = input("phone");
        $status = input("status");
        $code = input("code");
        // 获取用户信息
        $user = $this -> auth -> getUser();
        // 验证
        if(!$back_name){
            $this -> error("请选择银行卡",'back_name');
        }
        if(!$user_name){
            $this -> error("请输入持卡人名称",'user_name');
        }
        if(!$back_no){
            $this -> error("请输入银行卡卡号",'back_no');
        }
        if(!$back_branch){
            $this -> error("请输入支行信息",'back_branch');
        }
        if(!$phone){
            $this -> error("请输入手机号",'phone');
        }
        if (!Validate::regex($phone, "^1\d{10}$")) {
            $this->error(__('Phone format is incorrect'));
        }
        if (!Validate::regex($back_no, "^([1-9]{1})(\d{15}|\d{16}|\d{18})$")) {
            $this->error("银行卡号格式不正确");
        }
        // 判断银行卡是否已绑定
        $user_back_data = UserBack::where(['back_no' => $back_no,'deletetime' => null]) -> find();

        if($user_back_data){
            $this->error("当前银行卡已经绑定，请重新输入");
        }
        // 验证验证码
        /* if(!$code){
             $this -> error("请输入验证码",'code');
         }
         $ret = Sms::check($user['mobile'], $code, 'add_back');
         if (!$ret) {
             $this->error(__('Captcha is incorrect'));
         }*/
        $data = [
            'user_id' => $user['id'],
            'back_name' => $back_name,
            'user_name' => $user_name,
            'back_no' => $back_no,
            'back_branch' => $back_branch,
            'phone' => $phone,
            'status' => $status,
            'createtime' => time(),
        ];
        if($status == 1){
            // 判断之前是否已经设置过默认
            $arr = UserBack::where(['status' => 1,'user_id' => $user['id'],'deletetime' => null]) -> find();
            if($arr){
                UserBack::where('user_id',$user['id']) -> update(['status' => 0]);
            }
        }

        $i = UserBack::insert($data);
        if($i){
            $this -> success("添加成功");
        }else{
            $this -> error("添加失败");
        }
    }


    /**
     * 删除银行卡
     * @param string $token 用户唯一标识
     * @param int $id 银行卡id
     *
     */
    public function delete_user_back(){
        $id = input("id");

        $data = [
            'deletetime' => time()
        ];

        $i = UserBack::where('id',$id) -> update($data);
        if($i){
            $this -> success("删除成功");
        }else{
            $this -> error("删除失败");
        }
    }


    public function test(){
        $data = $this -> auth -> user_write_off(22,1000);
        $this -> success("ok",$data);
    }


    /**
     * 第三方登录
     *
     * @param string $platform 平台名称
     * @param string $code     Code码
     */
    private function third()
    {
        $url = url('user/index');
        $platform = $this->request->request("platform");
        $code = $this->request->request("code");
        $config = get_addon_config('third');
        if (!$config || !isset($config[$platform])) {
            $this->error(__('Invalid parameters'));
        }
        $app = new \addons\third\library\Application($config);
        //通过code换access_token和绑定会员
        $result = $app->{$platform}->getUserInfo(['code' => $code]);
        if ($result) {
            $loginret = \addons\third\library\Service::connect($platform, $result);
            if ($loginret) {
                $data = [
                    'userinfo'  => $this->auth->getUserinfo(),
                    'thirdinfo' => $result
                ];
                $this->success(__('Logged in successful'), $data);
            }
        }
        $this->error(__('Operation failed'), $url);
    }


    /**
     * 实名认证身份证上传
     *
     * @param string $platform 平台名称
     * @param string $code     Code码
     */
    public function UploadIdCard()
    {
        $user = $this->auth->getUser();
        if ($user['real_status'] == 1  ){
            $this->error('您已经实名认证,无需重复认证');
        }

        $num = $this->request->post("num") ;

        $attachment = null;
        //默认普通上传文件
        $file = $this->request->file('file');
        try {
            $upload = new Upload($file);
            $attachment = $upload->upload();
        } catch (UploadException $e) {
            $this->error($e->getMessage());
        }
        $bd = new BdOCR;
        $id_data = $bd->request_post($attachment);//请求百度OCR文字识别接口


        if (isset($id_data['image_status'])){

            $code = ['non_idcard','blurred','other_type_card','over_exposure','over_dark','unknown']; //响应状态码

            if (in_array($id_data['image_status'],$code)){

                if (file_exists(ROOT_PATH .'public'.$attachment->url)){
                    unlink(ROOT_PATH .'public'.$attachment->url);
                    $this->error(__($id_data['image_status']));
                }
            }
            if ($num == 1 && $id_data['image_status'] == 'normal' ){

                $res['real_name'] = $id_data['words_result']['姓名']['words'];
                $res['id_card'] = $id_data['words_result']['公民身份号码']['words'];
                $res['id_card_zm'] = $attachment->url;


                $result = $user->where('id_card',$res['id_card'])
                    ->field('id')
                    ->find();
                if($result){
                    unlink(ROOT_PATH .'public'.$attachment->url);
                    $this->error(__("ID card already exists"));
                }

            }else if($num == 2 && $id_data['image_status'] == 'reversed_side'){

                $res['id_card_fm'] =$attachment->url;

            }else{
                $this->error('请查看上传页面是否正确');
                unlink(ROOT_PATH .'public'.$attachment->url);
            }

        }else{
            $this->error('请重新上传');
            if (file_exists(ROOT_PATH .'public'.$attachment->url)){
                unlink(ROOT_PATH .'public'.$attachment->url);
            }

        }
        $res['url'] = $attachment->url;
        $res['fullurl'] = cdnurl($attachment->url, true);

        $this->success(__('Uploaded successful'),$res);

    }



}