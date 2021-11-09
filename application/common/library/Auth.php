<?php

namespace app\common\library;

use addons\epay\library\Service;
use app\admin\controller\user\Moneyexamine;
use app\admin\model\Goods;
use app\admin\model\HospitalAttrKey;
use app\admin\model\HospitalAttrVal;
use app\admin\model\HospitalSku;
use app\admin\model\UserAchievement;
use app\common\model\hospital\Banner;
use app\common\model\hospital\Order;
use app\common\model\hospital\Shop;
use app\common\model\hospital\Sku;
use app\common\model\hospital\Type;
use app\common\model\HotSearch;
use app\common\model\UserBonusLog;
use app\common\model\UserMoneyLog;
use app\common\model\User;
use app\common\model\UserRule;
use app\common\model\UserAddress;
use app\common\model\UserPaymentMethod;
use app\common\model\UserScoreLog;
use app\common\model\WithdrawalLog;
use fast\Random;
use think\Config;
use think\console\command\Build;
use think\Db;
use think\Exception;
use think\Hook;
use think\Request;
use think\Validate;
use think\Log;

class Auth
{
    protected static $instance = null;
    protected $_error = '';
    protected $_logined = false;
    protected $_user = null;
    protected $_token = '';
    //Token默认有效时长
    protected $keeptime = 2592000;
    protected $requestUri = '';
    protected $rules = [];
    //默认配置
    protected $config = [];
    protected $options = [];
    protected $allowFields = ['id','fictitious_id', 'username', 'nickname', 'mobile', 'level', 'avatar', 'bj_img','money', 'score','bonus'];

    public function __construct($options = [])
    {
        if ($config = Config::get('user')) {
            $this->config = array_merge($this->config, $config);
        }
        $this->options = array_merge($this->config, $options);
    }

    /**
     *

     */
    public static function instance($options = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($options);
        }

        return self::$instance;
    }

    /**
     * 获取User模型
     * @return User
     */
    public function getUser()
    {
        return $this->_user;
    }

    /**
     * 兼容调用user模型的属性
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->_user ? $this->_user->$name : null;
    }

    /**
     * 兼容调用user模型的属性
     */
    public function __isset($name)
    {
        return isset($this->_user) ? isset($this->_user->$name) : false;
    }

    /**
     * 根据Token初始化
     *
     * @param string $token Token
     * @return boolean
     */
    public function init($token)
    {
        if ($this->_logined) {
            return true;
        }
        if ($this->_error) {
            return false;
        }
        $data = Token::get($token);
        if (!$data) {
            return false;
        }
        $user_id = intval($data['user_id']);
        if ($user_id > 0) {
            $user = User::get($user_id);
            if (!$user) {
                $this->setError('Account not exist');
                return false;
            }
            if ($user['status'] != 'normal') {
                $this->setError('Account is locked');
                return false;
            }
            $this->_user = $user;
            $this->_logined = true;
            $this->_token = $token;

            //初始化成功的事件
            Hook::listen("user_init_successed", $this->_user);

            return true;
        } else {
            $this->setError('You are not logged in');
            return false;
        }
    }

    /**
     * 注册用户
     *
     * @param string $username 用户名
     * @param string $password 密码
     * @param string $email    邮箱
     * @param string $mobile   手机号
     * @param array  $extend   扩展参数
     * @return boolean
     */
    public function register($mobile, $password, $username = '', $email = '', $extend = [])
    {
        $ip = request()->ip();
        $time = time();
        // 获取虚拟id
        do{
            $fictitious_id = Random::numeric(6);
            // 判断该id是否存在
            $u = \app\admin\model\User::where('fictitious_id',$fictitious_id) -> find();
            if(!$u){
                break;
            }
        }while(0);
        $data = [
            'fictitious_id' => $fictitious_id,
            'username' => $mobile,
            'password' => $password,
            'email'    => $email,
            'mobile'   => $mobile,
            'level'    => 1,
            'score'    => 0,
            'avatar'   => "/uploads/avatar.png",
        ];
        $params = array_merge($data, [
//            'nickname'  => preg_match("/^1[3-9]{1}\d{9}$/",$username) ? substr_replace($username,'****',3,4) : $username,
            'nickname'  => "普通会员",
            'salt'      => Random::alnum(),
            'jointime'  => $time,
            'joinip'    => $ip,
            'logintime' => $time,
            'loginip'   => $ip,
            'prevtime'  => $time,
            'my_invitation_code'  => Random::numericAndAllCapital(8),
            'status'    => 'normal'
        ]);
        $params['password'] = $this->getEncryptPassword($password, $params['salt']);
        $params = array_merge($params, $extend);

        //账号注册时需要开启事务,避免出现垃圾数据
        Db::startTrans();
        try {
            $user = User::create($params, true);
            // 二次验证用户是否存在
            $user_data = User::where(['mobile' => $mobile]) -> select();
            if(count($user_data) > 1){
                Db::rollback();
                return false;
            }

            $this->_user = User::get($user->id);

            //设置Token
            $this->_token = Random::uuid();
            Token::set($this->_token, $user->id, $this->keeptime);

            //设置登录状态
            $this->_logined = true;

            //注册成功的事件
            Hook::listen("user_register_successed", $this->_user, $data);
            Db::commit();
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            Db::rollback();
            return false;
        }
        return true;
    }

    /**
     * 被邀请注册用户
     *
     * @param string $mobile   手机号
     * @param string $password 密码
     * @param string $user_id    邀请人id
     * @param array  $extend   扩展参数
     * @return boolean
     */
    public function invitation_register($mobile, $password, $user_id, $extend = [])
    {
        $ip = request()->ip();
        $time = time();
        // 获取虚拟id
        do{
            $fictitious_id = Random::nozero(6);
            // 判断该id是否存在
            $u = \app\admin\model\User::where('fictitious_id',$fictitious_id) -> find();
            if(!$u){
                break;
            }
        }while(0);

        $data = [
            'fictitious_id' => $fictitious_id,
            'username' => $mobile,
            'password' => $password,
            'mobile'   => $mobile,
            'level'    => 1,
            'score'    => 0,
            'avatar'   => "/uploads/avatar.png",
        ];
        $params = array_merge($data, [
//            'nickname'  => preg_match("/^1[3-9]{1}\d{9}$/",$username) ? substr_replace($username,'****',3,4) : $username,
            'nickname'  => "普通会员",
            'salt'      => Random::alnum(),
            'jointime'  => $time,
            'joinip'    => $ip,
            'logintime' => $time,
            'loginip'   => $ip,
            'prevtime'  => $time,
            'p_id'  => $user_id,
            'my_invitation_code'  => Random::numericAndAllCapital(8),
            'status'    => 'normal'
        ]);
        $params['password'] = $this->getEncryptPassword($password, $params['salt']);
        $params = array_merge($params, $extend);

        //账号注册时需要开启事务,避免出现垃圾数据
        Db::startTrans();
        try {
            // 保存用户数据
            $user = User::create($params, true);
            // 二次验证用户是否存在
            $user_data = User::where(['mobile' => $mobile]) -> select();
            if(count($user_data) > 1){
                Db::rollback();
                return false;
            }
            // 推荐人直推,团队增加
            User::where('id', $user_id) -> setInc('my_zt',1);
            User::where('id', $user_id) -> setInc('my_team',1);

            $this->_user = User::get($user->id);

            //设置Token
            $this->_token = Random::uuid();
            Token::set($this->_token, $user->id, $this->keeptime);

            //设置登录状态
            $this->_logined = true;

            //注册成功的事件
            Hook::listen("user_invitation_register_successed", $this->_user, $data);
            Db::commit();
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            Db::rollback();
            return false;
        }
        return true;
    }

    /**
     * 用户登录
     *
     * @param string $account  账号,用户名、邮箱、手机号
     * @param string $password 密码
     * @return boolean
     */
    public function login($account, $password)
    {
        $field = Validate::is($account, 'email') ? 'email' : (Validate::regex($account, '/^1\d{10}$/') ? 'mobile' : 'username');
        $user = User::get([$field => $account]);
        if (!$user) {
            $this->setError('Account is incorrect');
            return false;
        }

        if ($user->status != 'normal') {
            $this->setError('Account is locked');
            return false;
        }
        if ($user->password != $this->getEncryptPassword($password, $user->salt)) {
            $this->setError('Password is incorrect');
            return false;
        }

        //直接登录会员
        $this->direct($user->id);

        return true;
    }

    /**
     * 退出
     *
     * @return boolean
     */
    public function logout()
    {
        if (!$this->_logined) {
            $this->setError('You are not logged in');
            return false;
        }
        // 开启事务
        Db::startTrans();
        try {
            //设置登录标识
            $this->_logined = false;
            //删除Token表中的数据
            Token::delete($this->_token);
            // 删除用户表中的token
            User::where('id',$this -> _user -> id) -> update(['token' => '']);
            //退出成功的事件
            Db::commit();
            return true;
        } catch (Exception $e) {
            Db::rollback();
            $this->setError($e->getMessage());
            return false;
        }

    }

    /**
     * 修改密码
     * @param string $newpassword       新密码
     * @param string $oldpassword       旧密码
     * @param bool   $ignoreoldpassword 忽略旧密码
     * @return boolean
     */
    public function changepwd($newpassword, $oldpassword = '', $ignoreoldpassword = false)
    {
        // 判断是否登录
        if (!$this->_logined) {
            $this->setError('You are not logged in');
            return false;
        }
        //判断旧密码是否正确
        if ($this->_user->password == $this->getEncryptPassword($oldpassword, $this->_user->salt) || $ignoreoldpassword) {
            Db::startTrans();
            try {
                $salt = Random::alnum();
                $newpassword = $this->getEncryptPassword($newpassword, $salt);
                $this->_user->save(['loginfailure' => 0, 'password' => $newpassword, 'salt' => $salt]);

                Token::delete($this->_token);
                //修改密码成功的事件
                Hook::listen("user_changepwd_successed", $this->_user);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->setError($e->getMessage());
                return false;
            }
            return true;
        } else {
            $this->setError('Password is incorrect');
            return false;
        }
    }

    /**
     * 修改支付密码
     * @param string $newpassword       新密码
     * @param string $oldpassword       旧密码
     * @param bool   $ignoreoldpassword 忽略旧密码
     * @return boolean
     */
    public function changepaypwd($newpassword, $oldpassword = '', $ignoreoldpassword = false)
    {
        // 判断是否登录
        if (!$this->_logined) {
            $this->setError('You are not logged in');
            return false;
        }
        // 判断旧支付密码是否为空
        if($this->_user->pay_pwd == ''){
            // 开启事务
            Db::startTrans();
            try {
                $salt = Random::alnum();
                $newpassword = $this->getEncryptPassword($newpassword, $salt);
                $this->_user->save(['pay_pwd' => $newpassword, 'pay_salt' => $salt]);

                Token::delete($this->_token);
                //修改支付密码成功的事件
                Hook::listen("user_changepaypwd_successed", $this->_user);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->setError($e->getMessage());
                return false;
            }
            return true;
        }
        //判断旧支付密码是否正确
        if ($this->_user->pay_pwd == $this->getEncryptPassword($oldpassword, $this->_user->salt) || $ignoreoldpassword) {
            Db::startTrans();
            try {
                $salt = Random::alnum();
                $newpassword = $this->getEncryptPassword($newpassword, $salt);
                $this->_user->save(['pay_pwd' => $newpassword, 'pay_salt' => $salt]);

                Token::delete($this->_token);
                //修改支付密码成功的事件
                Hook::listen("user_changepaypwd_successed", $this->_user);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->setError($e->getMessage());
                return false;
            }
            return true;
        } else {
            $this->setError('Password is incorrect');
            return false;
        }
    }

    /**
     * 直接登录账号
     * @param int $user_id
     * @return boolean
     */
    public function direct($user_id)
    {
        $user = User::get($user_id);
        if ($user) {
            Db::startTrans();
            try {
                $ip = request()->ip();
                $time = time();

                //判断连续登录和最大连续登录
                if ($user->logintime < \fast\Date::unixtime('day')) {
                    $user->successions = $user->logintime < \fast\Date::unixtime('day', -1) ? 1 : $user->successions + 1;
                    $user->maxsuccessions = max($user->successions, $user->maxsuccessions);
                }

                $user->prevtime = $user->logintime;
                //记录本次登录的IP和时间
                $user->loginip = $ip;
                $user->logintime = $time;
                //重置登录失败次数
                $user->loginfailure = 0;
                $token = Random::uuid();
                // 记录token
                $user -> token = $token;

                $user->save();

                $this->_user = $user;

                $this->_token = $token;
                Token::set($this->_token, $user->id, $this->keeptime);

                $this->_logined = true;

                //登录成功的事件
                Hook::listen("user_login_successed", $this->_user);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->setError($e->getMessage());
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * 检测是否是否有对应权限
     * @param string $path   控制器/方法
     * @param string $module 模块 默认为当前模块
     * @return boolean
     */
    public function check($path = null, $module = null)
    {
        if (!$this->_logined) {
            return false;
        }

        $ruleList = $this->getRuleList();
        $rules = [];
        foreach ($ruleList as $k => $v) {
            $rules[] = $v['name'];
        }
        $url = ($module ? $module : request()->module()) . '/' . (is_null($path) ? $this->getRequestUri() : $path);
        $url = strtolower(str_replace('.', '/', $url));
        return in_array($url, $rules) ? true : false;
    }

    /**
     * 判断是否登录
     * @return boolean
     */
    public function isLogin()
    {
        if ($this->_logined) {
            return true;
        }
        return false;
    }

    /**
     * 获取当前Token
     * @return string
     */
    public function getToken()
    {
        return $this->_token;
    }

    /**
     * 获取会员基本信息
     */
    public function getUserinfo()
    {
        $data = $this->_user->toArray();
        $allowFields = $this->getAllowFields();
        $userinfo = array_intersect_key($data, array_flip($allowFields));
        $userinfo = array_merge($userinfo, Token::get($this->_token));
        return $userinfo;
    }

    /**
     * 获取会员组别规则列表
     * @return array
     */
    public function getRuleList()
    {
        if ($this->rules) {
            return $this->rules;
        }
        $group = $this->_user->group;
        if (!$group) {
            return [];
        }
        $rules = explode(',', $group->rules);
        $this->rules = UserRule::where('status', 'normal')->where('id', 'in', $rules)->field('id,pid,name,title,ismenu')->select();
        return $this->rules;
    }

    /**
     * 获取当前请求的URI
     * @return string
     */
    public function getRequestUri()
    {
        return $this->requestUri;
    }

    /**
     * 设置当前请求的URI
     * @param string $uri
     */
    public function setRequestUri($uri)
    {
        $this->requestUri = $uri;
    }

    /**
     * 获取允许输出的字段
     * @return array
     */
    public function getAllowFields()
    {
        return $this->allowFields;
    }

    /**
     * 设置允许输出的字段
     * @param array $fields
     */
    public function setAllowFields($fields)
    {
        $this->allowFields = $fields;
    }

    /**
     * 删除一个指定会员
     * @param int $user_id 会员ID
     * @return boolean
     */
    public function delete($user_id)
    {
        $user = User::get($user_id);
        if (!$user) {
            return false;
        }
        Db::startTrans();
        try {
            // 删除会员
            User::destroy($user_id);
            // 删除会员指定的所有Token
            Token::clear($user_id);

            Hook::listen("user_delete_successed", $user);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->setError($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * 获取密码加密后的字符串
     * @param string $password 密码
     * @param string $salt     密码盐
     * @return string
     */
    public function getEncryptPassword($password, $salt = '')
    {
        return md5(md5($password) . $salt);
    }

    /**
     * 检测当前控制器和方法是否匹配传递的数组
     *
     * @param array $arr 需要验证权限的数组
     * @return boolean
     */
    public function match($arr = [])
    {
        $request = Request::instance();
        $arr = is_array($arr) ? $arr : explode(',', $arr);
        if (!$arr) {
            return false;
        }
        $arr = array_map('strtolower', $arr);
        // 是否存在
        if (in_array(strtolower($request->action()), $arr) || in_array('*', $arr)) {
            return true;
        }

        // 没找到匹配
        return false;
    }

    /**
     * 设置会话有效时间
     * @param int $keeptime 默认为永久
     */
    public function keeptime($keeptime = 0)
    {
        $this->keeptime = $keeptime;
    }

    /**
     * 渲染用户数据
     * @param array  $datalist  二维数组
     * @param mixed  $fields    加载的字段列表
     * @param string $fieldkey  渲染的字段
     * @param string $renderkey 结果字段
     * @return array
     */
    public function render(&$datalist, $fields = [], $fieldkey = 'user_id', $renderkey = 'userinfo')
    {
        $fields = !$fields ? ['id', 'nickname', 'level', 'avatar'] : (is_array($fields) ? $fields : explode(',', $fields));
        $ids = [];
        foreach ($datalist as $k => $v) {
            if (!isset($v[$fieldkey])) {
                continue;
            }
            $ids[] = $v[$fieldkey];
        }
        $list = [];
        if ($ids) {
            if (!in_array('id', $fields)) {
                $fields[] = 'id';
            }
            $ids = array_unique($ids);
            $selectlist = User::where('id', 'in', $ids)->column($fields);
            foreach ($selectlist as $k => $v) {
                $list[$v['id']] = $v;
            }
        }
        foreach ($datalist as $k => &$v) {
            $v[$renderkey] = isset($list[$v[$fieldkey]]) ? $list[$v[$fieldkey]] : null;
        }
        unset($v);
        return $datalist;
    }

    /**
     * 设置错误信息
     *
     * @param $error 错误信息
     * @return Auth
     */
    public function setError($error)
    {
        $this->_error = $error;
        return $this;
    }

    /**
     * 获取错误信息
     * @return string
     */
    public function getError()
    {
        return $this->_error ? __($this->_error) : '';
    }

    /**
     * 获取用户地址
     * @return array
     */
    public function getAddress()
    {
        $this->address = UserAddress::where('user_id', $this->_user->id)
            ->where('is_del','=',0)
            ->order('type','desc')
            ->order('createtime','desc')
            ->select();
        return $this->address;
    }

    /**
     * 新增地址
     * @param string $name   收件人
     * @param string $phone   联系电话
     * @param string $province_city_area   省市区
     * @param string $address   详细地址
     * @param string $type   是否默认：1默认 0未默认
     * @return boolean
     */
    public function addAddress($name,$phone,$province_city_area,$address,$type)
    {
        // 获取提交的数据
        $params = [
            'user_id' => $this->_user->id,
            'name' => $name,
            'phone' => $phone,
            'province_city_area'    => $province_city_area,
            'address'   => $address,
            'type' => $type
        ];
        //账号注册时需要开启事务,避免出现垃圾数据
        Db::startTrans();
        try {
            // 判断新地址是否是默认
            if($type == 1){
                // 判断当前用户是否拥有已默认的地址
                $i = UserAddress::where('user_id', $this->_user->id)
                    ->where('type','=',1)->field('id')->find();
                // 如果存在则将其更改为不默认
                if($i){
                    $id = UserAddress::get($i->id);
                    $id -> type = 0;
                    $id -> save();
                }
            }
            // 添加地址表
            $user = UserAddress::create($params, true);

            //新增成功的事件
            Hook::listen("user_add_address", $this->_user, $params);
            Db::commit();
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            Db::rollback();
            return false;
        }
        return true;
    }


    /**
     * 修改收获地址
     * @param string $address_id   收获地址ID
     * @param string $name   收件人
     * @param string $phone   联系电话
     * @param string $province_city_area   省市区
     * @param string $address   详细地址
     * @param string $type   是否默认：1默认 0未默认
     * @return boolean
     */
    public function updAddress($address_id,$name,$phone,$province_city_area,$address,$type)
    {
        // 获取提交的数据
        $params = [
            'name' => $name,
            'phone' => $phone,
            'province_city_area'    => $province_city_area,
            'address'   => $address,
            'type' => $type
        ];
        //账号注册时需要开启事务,避免出现垃圾数据
        Db::startTrans();
        try {
            // 判断修改地址是否是默认
            if($type == 1){
                // 判断当前用户是否拥有已默认的地址
                $i = UserAddress::where('user_id', $this->_user->id)
                    ->where('type','=',1)->field('id')->find();
                // 如果存在则将其更改为不默认
                if($i){
                    $id = UserAddress::get($i->id);
                    $id -> type = 0;
                    $id -> save();
                }
            }
            // 修改地址表
            $address_data = UserAddress::get($address_id);
            $address_data -> name = $name;
            $address_data -> phone = $phone;
            $address_data -> province_city_area = $province_city_area;
            $address_data -> address = $address;
            $address_data -> type = $type;
            $address_data -> save();

            //新增成功的事件
            Hook::listen("user_upd_address", $this->_user, $params);
            Db::commit();
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            Db::rollback();
            return false;
        }
        return true;
    }


    /**
     * 删除收获地址
     * @param string $address_id   收获地址ID
     * @return boolean
     */
    public function delAddress($address_id)
    {
        //账号注册时需要开启事务,避免出现垃圾数据
        Db::startTrans();
        try {

            // 修改地址表
            $address_data = UserAddress::get($address_id);
            $address_data -> is_del = 1;
            $address_data -> save();

            //新增成功的事件
            Hook::listen("user_del_address", $this->_user, $address_data);
            Db::commit();
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            Db::rollback();
            return false;
        }
        return true;
    }


    /**
     * 查询收款方式
     *
     * @param string $type   收款类型：1微信,2支付宝
     * @return array
     */
    public function getPaymentMethod($type)
    {
        $this->address = UserPaymentMethod::where('user_id', $this->_user->id)
            ->where('type','=',$type)
            ->where('is_del','=',0)
            ->order('type','desc')
            ->order('createtime','desc')
            ->select();
        return $this->address;
    }


    /**
     * 添加收款方式
     *
     * @param string $type   收款类型：1微信,2支付宝
     * @param string $name   姓名
     * @param string $phone   账号
     * @param string $url   二维码相对路径
     * @return boolean
     */
    public function addPaymentMethod($type,$name,$url,$phone)
    {
        // 获取提交的数据
        $params = [
            'user_id' => $this->_user->id,
            'type' => $type,
            'name' => $name,
            'phone'    => $phone,
            'url'   => $url
        ];
        //账号注册时需要开启事务,避免出现垃圾数据
        Db::startTrans();
        try {
            // 添加地址表
            $user = UserPaymentMethod::insert($params);

            //新增成功的事件
            Db::commit();
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            Db::rollback();
            return false;
        }
        return true;
    }


    /**
     * 删除收获地址
     * @param string $payment_method_id   收款方式ID
     * @return boolean
     */
    public function delPaymentMethod($payment_method_id)
    {
        //账号注册时需要开启事务,避免出现垃圾数据
        Db::startTrans();
        try {

            // 修改地址表
            $address_data = UserPaymentMethod::get($payment_method_id);
            $address_data -> is_del = 1;
            $address_data -> save();

            //新增成功的事件
            Hook::listen("user_del_payment_method", $this->_user, $address_data);
            Db::commit();
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            Db::rollback();
            return false;
        }
        return true;
    }


    /**
     * 实名认证
     * @return array
     */
    public function getIdentityVerification()
    {
        $data = $this->_user->toArray();
        $allowFields = ['real_status','real_name','id_card','id_card_zm','id_card_fm'];
        $userinfo = array_intersect_key($data, array_flip($allowFields));
        return $userinfo;
    }


    /**
     * 保存实名信息
     * @param string $real_name  真实姓名
     * @param string $id_card  身份证号码
     * @param string $id_card_zm  身份证正面
     * @param string $id_card_fm  身份证反面
     * @return boolean
     */
    public function saveIdentityVerification($real_name,$id_card,$id_card_zm,$id_card_fm)
    {
        //账号注册时需要开启事务,避免出现垃圾数据
        Db::startTrans();
        try {
            // 判断该身份证号码是否存在

            $user_data = $this -> _user;
            // 修改用户表
            $user_data -> real_status = 1;
            $user_data -> real_name = $real_name;
            $user_data -> id_card = $id_card;
            $user_data -> id_card_zm = $id_card_zm;
            $user_data -> id_card_fm = $id_card_fm;
            $user_data -> nickname = $real_name;
            $user_data -> save();

            //新增成功的事件
            Hook::listen("user_save_identity_verification", $this->_user, $user_data);
            //增加推荐奖励事件
            if ($this -> _user->level == 1){
            Hook::exec('app\\api\\controller\\activity\\Recommend','creatRedPacket',$this->_user);
            }
            Db::commit();
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            Db::rollback();
            return false;
        }
        return true;
    }


    /**
     * 生成二维码
     * @param string $codeurl  二维码跳转路径
     * @param string $user_code  用户邀请码
     *
     */
    public function qrcode($codeurl,$user_code)
    {
        Vendor('phpqrcode.phpqrcode');
        $errorCorrectionLevel = 'L';  //容错级别
        $matrixPointSize = 5;      //生成图片大小

        // 判断文件夹是否存在，如果不存在则生成文件夹
        $destDir = ROOT_PATH.'public/uploads/phpqrcode/images/'.date("Ymd");
        if (!is_dir($destDir)) {
            mkdir($destDir);
        }

        //生成二维码图片路径
        $newfile = '/uploads/phpqrcode/images/'.date("Ymd").'/'.$user_code.'.png';

//        $filename = VENDOR_PATH.$newfile;
        $filename = ROOT_PATH.'public'.$newfile;
        \QRcode::png($codeurl,$filename , $errorCorrectionLevel, $matrixPointSize, 2);
        $QR = $filename;        //已经生成的原始二维码图片文件
        $QR = imagecreatefromstring(file_get_contents($QR));
        imagepng($QR,'qrcode.png');
        imagedestroy($QR);
        return $newfile;
    }

    /**
     * 我的团队
     * @param int $page 页数,默认为:1,每页5条数据
     * @return array
     */
    public function getUserTeam($page,$id = 0)
    {
        !empty($id)?$uid =$id:$uid=$this -> _user -> id;
        $pagesize = 8;  // 每页条数
        // 查询直推
        $user_zt = User::where('p_id', $uid)
            ->field('id,fictitious_id,nickname,prevtime,level,mobile,avatar,p_id')
            -> paginate($pagesize,'',['page' => $page, 'list_rows' =>$pagesize]);

        $zt_data = User::where('p_id', $uid)
            ->field('id')
            -> select();
        $count_user = count($zt_data);

//        Log::error($count_user);die;
        // 判断是否更新直推
        if($this -> _user -> my_zt != $count_user){
            $this->_user->save(['my_zt' => $count_user]);
        }
        $team_num = count($this->get_arrays($uid));
        // 判断是否更新团队
        if($this -> _user -> my_team != $team_num){
            $this->_user->save(['my_team' => $team_num]);
        }

        foreach ($user_zt as $k => $v){
            $user_zt[$k]['prevtime'] = date("Y-m-d H:i:s",$v['prevtime']);
        }
        
        $data = [
            'zt_num' => $count_user,
            'team_num' => $team_num,
            'user_data' => json_decode(json_encode($user_zt),true)['data']
        ];
        return $data;
    }

    /**
     * 递归查询
     */
    public function get_arrays($pid,$level=0){
        $res = User::where('p_id', $pid)
            ->field('id,p_id')
            ->select();

        $child = [];   // 定义存储子级数据数组
        foreach ($res as $key => $value) {
            if ($value['p_id'] == $pid) {
                $child[] = $value;   // 把子级数据添加进数组
                unset($res[$key]);  // 使用过后可以销毁
                $child = array_merge($child,$this->get_arrays($value['id'],$level+1));
            }
        }
        return $child;
    }

    /**
     * 我的余额
     * @return array
     */
    public function getMyMoney(){
        $my_money = $this -> _user -> money;
        // 获取余额最低提现
        $money_config = \app\common\model\Config::get(["id"=>26]);
        // 最多体现金额
        $max_money_cash = \app\common\model\Config::get(["id"=>28]);
        // 每天允许提现次数
        $cash_money_number = \app\common\model\Config::get(["id"=>27]);
        // 手续费
        $money_cash_charge = \app\common\model\Config::get(["id"=>31]);
        $data = [
            'my_money' => $my_money,
            'min_money_cash' =>$money_config->value,
            'max_money_cash' =>$max_money_cash->value,
            'cash_money_number' =>$cash_money_number->value,
            'money_cash_charge' =>$money_cash_charge->value,
        ];
        return $data;
    }

    /**
     * 余额详情
     * @return array
     */
    public function getMoneyLog(){
        $money_log = UserMoneyLog::where('user_id',$this -> _user -> id)
            -> field("id,user_id,money,before,after,memo,createtime")
            -> order('createtime','desc')
            -> select();
        foreach ($money_log as $k => $v){
            $user_data = \app\admin\model\User::where('id',$v['user_id']) -> find();
            $money_log[$k]['fictitious_id'] = $user_data['fictitious_id'];
            $money_log[$k]['createtime'] = date("Y-m-d H:i:s",$v['createtime']);
        }
        return $money_log;
    }



    /**
     * 我的积分
     * @return array
     */
    public function getMyScore(){
        $my_money = $this -> _user -> score;
        // 获取积分最低提现
        $money_config = \app\common\model\Config::get(["id"=>29]);
        // 手续费
        $integral_transfer_charge = \app\common\model\Config::get(["id"=>32]);
        $data = [
            'my_score' => $my_money,
            'min_score_cash' =>$money_config->value,
            'integral_transfer_charge' =>$integral_transfer_charge->value,
        ];
        return $data;
    }

    /**
     * 积分详情
     * @return array
     */
    public function getScoreLog(){
        $money_log = UserScoreLog::where('user_id',$this -> _user -> id)
            -> field("id,user_id,score,before,after,memo,createtime")
            -> order('createtime','desc')
            -> select();
        foreach ($money_log as $k => $v){
            $user_data = \app\admin\model\User::where('id',$v['user_id']) -> find();
            $money_log[$k]['fictitious_id'] = $user_data['fictitious_id'];
            $money_log[$k]['createtime'] = date("Y-m-d H:i:s",$v['createtime']);
        }
        return $money_log;
    }

    /**
     * 我的奖金
     * @return array
     */
    public function getMyBonus(){
        $my_money = $this -> _user -> bonus;
        // 获取奖金最低提现
        $money_config = \app\common\model\Config::get(["id"=>30]);
        // 手续费
        $bonus_transfer_charge = \app\common\model\Config::get(["id"=>33]);
        $data = [
            'my_bonus' => $my_money,
            'min_bonus_cash' =>$money_config->value,
            'bonus_transfer_charge' =>$bonus_transfer_charge->value,
        ];
        return $data;
    }

    /**
     * 奖金详情
     * @return array
     */
    public function getBonusLog(){
        $money_log = UserBonusLog::where('user_id',$this -> _user -> id)
            -> field("id,user_id,other_fictitious_id,bonus,before,after,memo,createtime")
            -> order('createtime','desc')
            -> select();
        foreach ($money_log as $k => $v){
            $user_data = \app\admin\model\User::where('id',$v['user_id']) -> find();
            $money_log[$k]['createtime'] = date("Y-m-d H:i:s",$v['createtime']);
        }
        return $money_log;
    }

    /**
     * 积分划转
     * @param string $num 划转数量
     * @param string $other_id 对方ID
     * @param string $pay_pwd 支付密码
     * @return bool
     */
    public function AddScore($num,$other_id,$pay_pwd){
        //账号注册时需要开启事务,避免出现垃圾数据
        Db::startTrans();
        try {
            // 当前用户积分
            $user_score = $this -> _user -> score;
            if($user_score < $num) return false;
            // 验证对方ID
            $other_data = User::where('fictitious_id',$other_id) -> field('id,fictitious_id,score') -> find();
            if(!$other_data) return false;
            // 验证支付密码
            if($this -> getEncryptPassword($pay_pwd,$this -> _user -> pay_salt) != $this -> _user -> pay_pwd) return false;
            // 手续费%
            $shouxu = \app\common\model\Config::get(['id' => 32]) -> value;
            // 扣除手续费之后的金额（向下取整）
            $num_s = floor($num - ($num*$shouxu/100));

            // 对方增加积分
             User::where('fictitious_id',$other_id) -> setInc('score',$num_s);

            // 添加记录
            $this->add_score_log($num_s,'+',$other_data['id'],'【转入】'.'对方ID：'.$this -> _user -> fictitious_id);

            // 扣除当前用户积分
             $this -> _user->setDec('score',$num) ;

            // 添加记录
            $this->add_score_log($num,'-',$this -> _user -> id,'【转出】'.'对方ID：'.$other_id);


            //注册成功的事件
            Db::commit();
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            Db::rollback();
            return false;
        }
        return true;
    }

    /**
     * 添加积分记录
     * @param int    $score   积分
     * @param string $code    备注
     * @param int    $user_id 对方会员ID
     * @param string $memo    备注
     */
    public function add_score_log($score,$code, $user_id, $memo){

        $user = User::where('id',$user_id) -> field('score') -> find();
        if ($user && $score != 0) {
            if($code == "+"){
                $before = $user->score - $score;
                $after = $user->score;
            }else{
                $before = $user->score + $score;
                $after = $user->score;
            }
            //更新会员信息
//            $user->save(['score' => $after, 'level' => $level]);
//            $user->save(['score' => $after]);
            //写入日志
            WithdrawalLog::create(['user_id' => $user_id,
                'score' => $code.$score, 'before' => $before,
                'after' => $after, 'memo' => $memo,
                'type' => 2]);

        }

    }

    /**
     * @return array
    */
    public function SelectScoreLog(){
        $log_data = WithdrawalLog::where('user_id',$this -> _user -> id)
            -> where('type','=',2)
            -> order("createtime",'desc')
            -> select();
        foreach ($log_data as $k => $v){
            $user_data = \app\admin\model\User::where('id',$v['user_id']) -> find();
            $log_data[$k]['fictitious_id'] = $user_data['fictitious_id'];
            $log_data[$k]['createtime'] = date("Y-m-d H:i:s",$v['createtime']);
            if($v['score'] > 0){
                $log_data[$k]['type'] = 1;
            }else if($v['score'] < 0){
                $log_data[$k]['type'] = 2;
            }
        }
        return $log_data;
    }

    /**
     * @return array
    */
    public function SelectMoneyLog(){
        $log_data = \app\admin\model\Moneyexamine::where('user_id',$this -> _user -> id)
            -> order("createtime",'desc')
            -> select();
        foreach ($log_data as $k => $v){
            $user_data = \app\admin\model\User::where('id',$v['user_id']) -> find();
            $log_data[$k]['fictitious_id'] = $user_data['fictitious_id'];
            $log_data[$k]['createtime'] = date("Y-m-d H:i:s",$v['createtime']);

            if($v['pay_method'] == 1){
                $log_data[$k]['pay_method'] = "微信";
            }else if($v['pay_method'] == 2){
                $log_data[$k]['pay_method'] = "支付宝";
            }else if($v['pay_method'] == 3){
                $log_data[$k]['pay_method'] = "银行卡";
            }
        }
        return $log_data;
    }

    /**
     * @return array
    */
    public function SelectBonusLog(){
        $log_data = WithdrawalLog::where('user_id',$this -> _user -> id)
            -> where('type','=',3)
            -> order("createtime",'desc')
            -> select();
        foreach ($log_data as $k => $v){
            $user_data = \app\admin\model\User::where('id',$v['user_id']) -> find();
            $log_data[$k]['fictitious_id'] = $user_data['fictitious_id'];
            $log_data[$k]['createtime'] = date("Y-m-d H:i:s",$v['createtime']);
        }
        return $log_data;
    }



    /**
     * 奖金转出功能
     * @param string $num 划转数量
     * @return bool
     */
    public function AddBonus($num){
        //账号注册时需要开启事务,避免出现垃圾数据
        Db::startTrans();
        try {
            // 当前用户奖金
            $user_score = $this -> _user -> bonus;
            if($user_score < $num) return false;

            // 扣除当前用户奖金
            $user_data = $this -> _user;
            $user_data -> bonus = $user_score - $num;
            // 添加记录
            $this->add_bonus_log($num,"奖金转出");

            // 添加余额
            // 手续费%
            $shouxu = \app\common\model\Config::get(['id' => 33]) -> value;
            // 扣除手续费之后的金额（向下取整） 1000    2%      980
            $num_s = floor($num - ($num*$shouxu/100));
            // 当前用户余额
            $user_money = $this -> _user -> money;
            $user_data -> money = $user_money + $num_s;
            // 保存数据
            $user_data -> save();

            // 添加余额详情
            $data = [
                'user_id' => $this -> _user -> id,
                'money' => "+".$num_s,
                'before' => $user_data['money'],
                'after' => $user_money + $num_s,
                'memo' => "奖金转入",
                'createtime' => time()
            ];
            UserMoneyLog::insert($data);

            Db::commit();
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            Db::rollback();
            return false;
        }
        return true;
    }


    /**
     * 添加记录
     * @param int    $score   转出金额
     * @param string $memo    备注
     */
    public function add_bonus_log($score, $memo){
        $user = $this -> _user;
        if ($user && $score != 0) {
            $before = $user->bonus + $score;
            $after = $user->bonus;
            //写入日志
            WithdrawalLog::create(['user_id' => $user -> id,
                'score' => $score, 'before' => $before,
                'after' => $after, 'memo' => $memo,
                'type' => 3,'status' => 2]);
        }

    }


    /**
     * 获取banner图
     * @return array
     */
    public function query_banner(){
        $data = Banner::order('weigh','desc')
            -> limit(5)
            -> order('createtime','desc')
            -> select();
        return $data;

    }


    /**
     * 获取商品分类
     * @return array
     */
    public function goods_class(){
        $data = Type::order('weigh','desc')
            -> order('createtime','desc')
            -> select();
        return $data;

    }


    /**
     * 获取商品分类主图
     * @return array
     */
    public function type_image(){
        $data = config('site.hospital_type_image');
        return $data;

    }


    /**
     * 获取全部商品信息
     * @ApiReturn   ({
        'code':'1查询成功 401未登录',
        'msg':''
        'data' :{
            "goods_id": "商品ID",
            "type_id": "分类ID",
            "status": "状态:0=展示中,1=已兑完,2=仓库中",
            "goodsdata": {
                "name": "商品名称",
                "stock": "库存",
                "sales": "价格",
                "cover_image": "封面图",
            },
            "typedata": {
                "name": "类别名称"
            },
            "weigh": "权重",
        }
     })
     * @return array
     */
    public function goods_data($page,$type_id){
        $num = [];
        $pagesize = 7;  // 每页条数
        $w = [];
        if($type_id){
            $data = Goods::with(['goodsdata','typedata'])
                -> where('typedata.id',$type_id)
                -> order('weigh','desc')
                -> paginate($pagesize,'',['page' => $page, 'list_rows' =>$pagesize]);
        }else{
            $data = Goods::with(['goodsdata','typedata'])
                -> order('weigh','desc')
                -> paginate($pagesize,'',['page' => $page, 'list_rows' =>$pagesize]);
        }

        foreach ($data as $k => $v) {
            $v->visible(['id','goods_id','type_id','status','num','specs_data','stock','sales','weigh','createtime','updatetime','deletetime']);
            $v->visible(['goodsdata']);
            $v->getRelation('goodsdata')->visible(['name','cover_image']);
            $v->visible(['typedata']);
            $v->getRelation('typedata')->visible(['name']);

            // 查询商品价格表中最低的数据 sku
            $sku_data = Sku::where('item_id',$v['id'])
                -> order('sales')
                -> find();
            $num[$k]['sku_data'] = [
                'stock' => $sku_data['stock'],
                'sales' => $sku_data['sales'],
            ];
        }
        // 将对象转换成数组
        $data = \GuzzleHttp\json_decode(\GuzzleHttp\json_encode($data),true)['data'];
        foreach ($data as  $k => $v){
            // 获取商品信息
            $data[$k]['goodsdata'] = [
                'name' => $data[$k]['goodsdata']['name'],
                'cover_image' => $data[$k]['goodsdata']['cover_image'],
                'stock' => $num[$k]['sku_data']['stock'],
                'sales' => $num[$k]['sku_data']['sales'],
            ];
        }

        return $data;

    }


    /**
     * 分类列表
     * @ApiReturn   ({
        'code':'1查询成功 401未登录',
        'msg':''
        'data' :{
            "goods_id": "商品ID",
            "type_id": "分类ID",
            "status": "状态:0=展示中,1=已兑完,2=仓库中",
            "goodsdata": {
                "name": "商品名称",
                "stock": "库存",
                "sales": "价格",
                "cover_image": "封面图",
            },
            "typedata": {
                "name": "类别名称"
            },
            "weigh": "权重",
        }
     })
     * @return array
     */
    public function class_goods_data($type_id){
        $num = [];
        if($type_id){
            $data = Goods::with(['goodsdata','typedata'])
                -> where('typedata.id',$type_id)
                -> order('weigh','desc')
                -> select();
        }else{
            $data = Goods::with(['goodsdata','typedata'])
                -> order('weigh','desc')
                -> select();
        }
        foreach ($data as $k => $v) {
            $v->visible(['id','goods_id','type_id','status','num','specs_data','stock','sales','weigh','createtime','updatetime','deletetime']);
            $v->visible(['goodsdata']);
            $v->getRelation('goodsdata')->visible(['name','cover_image']);
            $v->visible(['typedata']);
            $v->getRelation('typedata')->visible(['name']);

            // 查询商品价格表中最低的数据 sku
            $sku_data = Sku::where('item_id',$v['id'])
                -> order('sales')
                -> find();
            $num[$k]['sku_data'] = [
                'stock' => $sku_data['stock'],
                'sales' => $sku_data['sales'],
            ];
        }
        // 将对象转换成数组
        $data = \GuzzleHttp\json_decode(\GuzzleHttp\json_encode($data),true);
        foreach ($data as  $k => $v){
            // 获取商品信息
            $data[$k]['goodsdata'] = [
                'name' => $data[$k]['goodsdata']['name'],
                'cover_image' => $data[$k]['goodsdata']['cover_image'],
                'stock' => $num[$k]['sku_data']['stock'],
                'sales' => $num[$k]['sku_data']['sales'],
            ];
        }

        return $data;

    }

    /**
     * 搜索界面-热门搜索
     * @return array
    */
    public function hot_search(){
        $data = HotSearch::where('type',1)
            ->order('frequency','desc')
            ->limit(8)
            ->field('id,name')
            ->select();
        return $data;
    }

    /**
     * 搜索界面-最近搜索
     * @return array
    */
    public function recent_search(){
        $data = HotSearch::where(['user_id'=>$this->_user->id,'type'=>2])
            ->order('id','desc')
            ->limit(8)
            ->field('id,name')
            ->select();
        return $data;
    }

    /**
     * 确认搜索功能
     * @param string $value 搜索内容
     * @param int $page 页数,默认为:1,每页10条数据
     * @param int $order 排序：(价格："sales desc"降序，"sales asc"升序)(销量："stock desc"降序，"stock asc"升序)
     * @return array
    */
    public function goods_search($value,$page,$order){
        $num = [];
        $pagesize = 10;  // 每页条数
        $data = Goods::with(['goodsdata','typedata'])
            -> where('goodsdata.name','like','%'.$value.'%')
            -> order($order)
            -> order('weigh','desc')
            -> order('createtime','desc')
            -> paginate($pagesize,'',['page' => $page, 'list_rows' =>$pagesize]);
        foreach ($data as $k => $v) {
            $v->visible(['id','goods_id','type_id','status','num','specs_data','stock','sales','weigh','createtime','updatetime','deletetime']);
            $v->visible(['goodsdata']);
            $v->getRelation('goodsdata')->visible(['name','cover_image']);
            $v->visible(['typedata']);
            $v->getRelation('typedata')->visible(['name']);

        }
        // 将对象转换成数组
        $data = \GuzzleHttp\json_decode(\GuzzleHttp\json_encode($data),true)['data'];
        // 添加记录时需要开启事务,避免出现垃圾数据
        Db::startTrans();
        try {
            if($value){
                // 判断该搜索的内容，热门搜索中是否存在
                $data1 = HotSearch::where(['name'=>$value,'type'=>1])
                    ->field('id,name')
                    ->find();
                if($data1){
                    HotSearch::where(["id"=>$data1["id"]])->setInc('frequency',1);
                }else{
                    //搜索表新增数据
                    HotSearch::create([
                        "name"=>$value,
                        "user_id" => '',
                        "frequency"=>1,
                        "type"=>1,
                        "createtime"=>time(),
                        ]);
                }
                // 判断用户是否登录
                if($this -> _user){
                    // 删除搜索表中的该数据
                    HotSearch::where(['name'=>$value,'type'=>2,'user_id'=>$this -> _user -> id])->delete();
                    // 新增
                    HotSearch::create([
                            'user_id' => $this -> _user -> id,
                            "name"=>$value,
                            "type"=>2,
                            "frequency"=>1,
                            "createtime"=>time(),
                        ]);
                }
                Db::commit();
            }

        } catch (Exception $e) {
            $this->setError($e->getMessage());
            Db::rollback();
            return false;
        }
        return $data;
    }


    /**
     * 商品详情
     * @param int $goods_id 商品ID
     * @return array
     */
    public function goods_details($goods_id){
        $data = Goods::where('goods.id',$goods_id)
            -> with(['goodsdata','typedata','shopdata'])
            -> select();

        foreach ($data as $k => $v) {
            $v->visible(['id','goods_id','type_id','status','num','remark','specs_data','stock','sales','weigh','createtime','updatetime','deletetime']);
            $v->visible(['goodsdata']);
            $v->getRelation('goodsdata')->visible(['name','cover_image','images']);
            $v->visible(['typedata']);
            $v->getRelation('typedata')->visible(['name']);
            $v->visible(['shopdata']);
            $v->getRelation('shopdata')->visible(['id','name']);
        }
        // 将对象转换成数组
        $data = \GuzzleHttp\json_decode(\GuzzleHttp\json_encode($data),true);
        foreach ($data as  $k => $v){
            $sku_data = Sku::where('item_id',$v['id'])
                -> order('sales')
                -> find();
            $sku_data_max = Sku::where('item_id',$v['id'])
                -> order('sales','desc')
                -> find();
            // 获取商品信息
            $data[$k]['goodsdata'] = [
                'name' => $data[$k]['goodsdata']['name'],
                'cover_image' => $data[$k]['goodsdata']['cover_image'],
                'images' => $data[$k]['goodsdata']['images'],
                'stock' => $sku_data_max['stock'] ? $sku_data_max['stock'] : $v['stock'],
                'sales_min' => $sku_data['sales'] ? $sku_data['sales'] : $v['sales'],
                'sales_max' => $sku_data_max['sales'] ? $sku_data_max['sales'] : $v['sales'],
                'shop_id' => $data[$k]['shopdata']['id'],
                'shop_name' => $data[$k]['shopdata']['name'],
                'remark' => $data[$k]['remark'],
            ];
        }
        return $data[0]['goodsdata'];
    }


    /**
     * 商品规格与属性
     * @param int $goods_id 商品ID
     * @return array
     */
    public function goods_spec_attr($goods_id){
        if(!$goods_id){
            return false;
        }
        // 获取当前商品的规格
        $spec = HospitalAttrKey::where('item_id',$goods_id)
            -> field('attr_key_id,attr_name')
            -> select();
        foreach ($spec as $k => $v){
            // 获取当前商品属性
            $attr = HospitalAttrVal::where('item_id',$goods_id)
                -> where('attr_key_id',$v['attr_key_id'])
                ->field('symbol,attr_key_id,attr_value')
                -> select();
            $spec[$k]['attr'] = $attr;
        }
        // 获取当前商品属性
        $attr = HospitalAttrVal::where('item_id',$goods_id)
            ->field('symbol,attr_key_id,attr_value')
            -> select();
        $return_data = [
            'spec' => $spec,
            'attr' => $attr
        ];
        return $spec;
    }


    /**
     * 商品规格与属性
     * @param int $goods_id 商品ID
     * @param string $string 属性ID按顺序组合成的字符串，用英文逗号(,)隔开
     * @return array
     */
    public function goods_price_select($goods_id,$string){
        if(!$goods_id){
            return false;
        }
        if(!$string){
            return false;
        }
        $sku_data = HospitalSku::where(['item_id'=>$goods_id,'attr_symbol_path' => $string])
            -> field('sku_id,stock,sales')
            -> find();

        return $sku_data;
    }


    /**
     * 创建医美订单
     * @param int $goods_id 商品ID
     * @param int $sku_id 属性值id
     * @param int $num 数量
     * @return array
     */
    public function create_hospital_order($goods_id,$sku_id = '',$num){
        if(!$goods_id){
            return false;
        }
        if(!$num){
            return false;
        }
        // 生成订单编号
        $order_no = "SN".date("Ymd").time().Random::numeric();
        // 查询商品信息
        $goods_hospital_data = \app\common\model\hospital\Goods::where('id',$goods_id) -> find();
        if(!$goods_hospital_data){
            return false;
        }
        // 获取商品名称
        $goods_data = \app\common\model\Goods::where('id',$goods_hospital_data['goods_id']) -> find();
        // 如果该商品有规格
        if($goods_hospital_data['specs_data'] == 1){
            // 商品属性价格信息
            $sku_data = HospitalSku::where(['sku_id' => $sku_id,'item_id' => $goods_id]) -> find();
            if(!$sku_data){
                return false;
            }
            // 获取属性名称ID数组
            $sku_arr = explode(',',$sku_data['attr_symbol_path']);
            // 查询属性表中对应的名称
            foreach ($sku_arr as $v){
                $var_data[] = HospitalAttrVal::where('symbol',$v) -> find()['attr_value'];
            }
            $sku_str = implode(',',$var_data);
            $one_money = $sku_data['sales'];
        }else{
            // 无规格
            $one_money = $goods_hospital_data['sales'];
            $sku_str = "";
        }

        $data = [
            'order_no' => $order_no,
            'user_id' => $this -> _user -> id,
            'hospital_goods_id' => $goods_id,
            'hospital_goods_name' => $goods_data['name'],
            'sku_str' => $sku_str,
            'one_money' => $one_money,
            'all_money' => $one_money*$num,
            'actual_money' => 0,
            'num' => $num,
            'status' => 0,
            'pay_status' => 0,
            'createtime' => time(),
        ];
        // 1.创建订单
        // 2.减少库存
        //账号注册时需要开启事务,避免出现垃圾数据
        Db::startTrans();
        try {
            // 创建订单
            Order::create($data);
            // 减少库存
            // 如果该商品有规格
            if($goods_hospital_data['specs_data'] == 1){
                HospitalSku::where('sku_id' , $sku_id) -> setDec('stock',$num);
            }else{
                \app\common\model\hospital\Goods::where('id',$goods_id) -> setDec('stock',$num);
            }


            Db::commit();
            return ['order_no'=>$order_no,'all_money' => $one_money*$num,'goods_name' => $goods_data['name']];
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            Db::rollback();
            return false;
        }
    }


    /**
     * 查询医美订单
     * @param int $order_no 订单编号
     * @return array
     */
    public function select_hospital_order($order_no){
        return "ok";
    }


    /**
     * 支付医美订单
     * @param string $token 用户唯一标识
     * @param string $order_id 订单id
     * @param string $order_no 订单编号
     * @param float $actual_money 订单支付金额
     * @param int $pay_method 支付方式:0=余额,1=微信,2=支付宝
     * @param int $pay_pwd 支付密码
     * @return array
     */
    public function pay_hospital_order($order_id,$actual_money,$pay_method,$pay_pwd = ''){
        if(!$order_id){
            return false;
        }
        if(!$actual_money){
            return false;
        }
        if(!$pay_method){
            return false;
        }
        // 查询商品信息
        $order_data = Order::where('id',$order_id) -> find();
        if(!$order_data){
            return false;
        }
        // 如果用户是余额支付
        if($pay_method == 0){
            // 判断支付密码是否正确
            if($this -> getEncryptPassword($pay_pwd,$this -> _user -> pay_salt) != $this -> _user -> pay_pwd) return "支付密码不正确";
        }

        $data = [
            'actual_money' => $actual_money,
            'status' => 1,
            'pay_status' => $pay_method,
            'paytime' => time(),
        ];
        //账号注册时需要开启事务,避免出现垃圾数据
        Db::startTrans();
        try {
            // 修改订单表中的内容
            Order::where('id',$order_id) -> update($data);
            // 如果使用余额支付，则扣除用户余额，并写入日志
            if($pay_method == 1){
                $this->edit_money_log($actual_money,"购买商品",'-');
            }

            // 添加业绩明细表

            Db::commit();
            return true;
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            Db::rollback();
            return false;
        }

    }

    /**
     * 操作用户余额，并写入日志
     * @param int    $score   金额
     * @param string $memo    备注
     * @param string $status  增加or减少
     */
    public function edit_money_log($score, $memo, $status){
        $user = $this -> _user;
        if ($user && $score != 0) {
            $before = $user->money; // 变更前
            if($status == '+'){
                $after = $user->money + $score; // 变更前
            }else{
                $after = $user->money - $score; // 变更后
            }
            // 修改用户余额
            $user -> money = $after;
            $user -> save();
            $data = [
                'user_id' => $user -> id,
                'money' => $status.$score,
                'before' => $before,
                'after' => $after,
                'memo' => $memo,
                'createtime' => time()
            ];
            //写入日志
            UserMoneyLog::insert($data);

        }

    }


    /**
     * 查看医美订单
     * @param int $page 页数,默认为:1,每页10条数据
     * @return array
     */
    public function see_hospital_order($status,$page){
        $pagesize = 5;  // 每页条数
        $order = new \app\admin\model\Order();
        $where = [];
        if($status != null){
            $where = array('status'=>$status);
        }
        $order_data = $order
            -> where($where)
            -> where('user_id',$this -> _user -> id)
            -> order('createtime','desc')
            -> paginate($pagesize,'',['page' => $page, 'list_rows' =>$pagesize]);
        $order_data = \GuzzleHttp\json_decode(\GuzzleHttp\json_encode($order_data),true)['data'];
        // 获取订单过期时间
        $over_time = \app\common\model\Config::get(['id' => 70])['value'];
        foreach ($order_data as $k => $v){
            // 查询医美商品信息
            $hospital_data = \app\common\model\hospital\Goods::where('id',$v['hospital_goods_id']) -> find();
            // 查询商品信息
            $goods_data = \app\common\model\Goods::where('id',$hospital_data['goods_id']) -> find();
            $order_data[$k]['hospital_goods_cover_image'] = $goods_data['cover_image'];
            if($v['status'] == 0){
                $order_data[$k]['over_time'] = $over_time;
                if(time() - $v['createtime'] > $over_time){
                    $order_data[$k]['over_status'] = 1;
                }else{
                    $order_data[$k]['over_status'] = 0;
                }
            }
            $order_data[$k]['createtime'] = date("Y-m-d H:i:s",$v['createtime']);
        }

        return $order_data;
    }


    /**
     * 去使用医美订单
     * @param int $order_id 订单id
     * @return array
     */
    public function use_hospital_order($order_id){
        $order_data = Order::where('id',$order_id) -> find();
        // 查询店铺id
        $shop_id = \app\common\model\hospital\Goods::where('id',$order_data['hospital_goods_id']) -> find()['shop_id'];
        // 查询店铺名称
        $shop_data = Shop::where('id',$shop_id) -> find();
        // 获取订单过期时间
        $over_time = \app\common\model\Config::get(['id' => 70])['value'];
        $order_data['over_time'] = $over_time;

        if(time() - $order_data['createtime'] > $over_time){
            $order_data['over_status'] = 1;
        }else{
            $order_data['over_status'] = 0;
        }

        $order_data['createtime'] = date("Y-m-d H:i:s",$order_data['createtime']);
        $order_data['paytime'] = date("Y-m-d H:i:s",$order_data['paytime']);

        $order_data['shop_data'] = $shop_data;
        // 查询医美商品信息
        $hospital_data = \app\common\model\hospital\Goods::where('id',$order_data['hospital_goods_id']) -> find();
        // 查询商品信息
        $goods_data = \app\common\model\Goods::where('id',$hospital_data['goods_id']) -> find();
        $order_data['hospital_goods_cover_image'] = $goods_data['cover_image'];
        return $order_data;
    }


    /**
     * 用户升级代理商
     * @param string $status 1用户购买代理商礼包，2用户购买抢购商品
     * @param int $order_id 订单id
     * @return array
     *
     */
    public function upgrade_agent($status){
        if(!$this -> _user){
            return false;
        }
        if(!$status){
            return false;
        }
        // 用户升级成为代理商之后，判断上级是否满足升级总代理条件：直推10个代理商

        // 一、用户购买代理商礼包

//        if($status == 1){
//            /*
//             * 用户购买代理商礼包
//             * 1.判断当前用户当前状态，如果不是消费者，则不能购买。
//             * 2.判断当前用户是否拥有5个直推用户
//             * 3.判断当前用户的5个直推用户是否都购买过抢购订单
//             *
//             * */
//            // 1.判断当前用户当前状态，如果不是消费者，则不能购买
//            if($this -> _user -> level == 2 || $this -> _user -> level == 3){
//                return false;
//            }
//
//            // 开启事务,避免出现垃圾数据
//            Db::startTrans();
//            try {
//
//                // 验证用户是否满足升级条件
//                $return_status = $this -> verification_user($this -> _user -> id);
//
//                // 如果满足升级条件，则对用户进行升级
//                if(!$return_status){
//                    return false;
//                }
//                // 对用户进行升级
//                $i = \app\admin\model\User::where('id',$this -> _user -> id) -> update(['level' => 2]);
//                // 升级成代理之后，判断当前用户上级是否是总代理，如果不是,则查询是否满足升级总代理条件，若满足则进行升级
//                $p_data = \app\admin\model\User::where('id',$this -> _user -> p_id) -> find();
//                if($p_data){
//                    if($p_data['level'] != 3){
//                        // 上级不是总代理,查询是否满足升级总代理条件，若满足则进行升级
//                        $this->zong_upgrade($this -> _user -> p_id);
//                    }
//                }
//
//                if($i){
//                    Db::commit();
//                    return true;
//                }else{
//                    Db::rollback();
//                    return false;
//                }
//
//            } catch (Exception $e) {
//                $this->setError($e->getMessage());
//                Db::rollback();
//                return false;
//            }
//
//        }

        // 二、下级用户购买抢购商品
        if($status == 2){
            /*
             * 用户购买抢购商品
             * 1.判断用户上级是否是消费者，如果不是则不升级
             * 2.如果用户上级为消费者，则判断上级用户是否拥有5个直推用户
             * 3.判断用户上级用户的5个直推用户是否都购买过抢购订单
             *
             * */

            // 获取上级用户数据
            $p_data = \app\admin\model\User::where('id',$this -> _user -> p_id) -> find();

            // 1.判断用户上级是否是消费者，如果不是则不升级
            if($p_data){
                // 上级用户为消费者
                if($p_data['level'] == 1){
                    // 开启事务,避免出现垃圾数据
                    Db::startTrans();
                    try {
                        // 验证用户是否满足升级条件
                        $return_status = $this -> verification_user($p_data['id']);

                        // 如果满足升级条件，则对用户进行升级
                        if(!$return_status){
                            return false;
                        }
                        // 对用户进行升级
                        $i = \app\admin\model\User::where('id',$p_data['id']) -> update(['level' => 2]);

                        // 升级成代理之后，判断当前用户上上级是否是总代理，如果不是,则查询是否满足升级总代理条件，若满足则进行升级
                        $p_data = \app\admin\model\User::where('id',$p_data['p_id']) -> find();
                        if($p_data){
                            if($p_data['level'] != 3){
                                // 上级不是总代理,查询是否满足升级总代理条件，若满足则进行升级
                                $this->zong_upgrade($this -> _user -> p_id);
                            }
                        }

                        if($i){
                            Db::commit();
                            return true;
                        }else{
                            Db::rollback();
                            return false;
                        }

                    } catch (Exception $e) {
                        $this->setError($e->getMessage());
                        Db::rollback();
                        return false;
                    }
                }
            }
        }

    }

    /**
     * 验证用户是否满足代理商升级条件
     * */
    protected function verification_user($user_id){
        // 用户是否购买代理商礼包 is_agent == 1
//        $user_data = \app\admin\model\User::where(['id'=>$user_id,'is_agent' => 1])
//            -> find();
//        if(!$user_data){
//            return false;
//        }
        // 判断当前用户是否拥有100个直推用户
        $p_data = \app\admin\model\User::where('p_id',$user_id)
            -> select();
        if(count($p_data) < 100){
            return false;
        }
        $num = 0;
        // 判断当前用户的100个直推用户是否都购买过抢购订单
        foreach ($p_data as $k => $v){
            $seckill_data[$k]['user'] = $v['id'];
            $seckill_order = \app\common\model\seckill\Order::where('status','egt',3)
                -> where('user_id',$v['id'])
                -> field('id')
                -> find();
            // 如果直推用户购买过抢购订单，则将统计变量num自增
            if($seckill_order){
                $seckill_data[$k]['data'] = $seckill_order;
                $num ++ ;
                // 如果用户已经满足5个抢购订单，则跳出循环
                if($num >= 100){
                    break;
                }
            }
        }
        if($num != 100){
            return false;
        }
        return $num;

    }

    /**
     * 验证用户是否满足总代理升级条件:直推10个代理商
     * */
    protected function zong_upgrade($user_id){
        // 总代理升级条件:直推10个代理商
        $p_dai_data = \app\admin\model\User::where(['p_id'=>$user_id,'level' => 2])
            -> select();
        $p_zong_data = \app\admin\model\User::where(['p_id'=>$user_id,'level' => 3])
            -> select();
        // 满足升级条件，升级总代理
        if(count($p_dai_data) + count($p_zong_data) >= 10){
            User::where('id',$user_id) -> update(['level' => 3]);
        }

    }

    /**
     * 用户购买抢购订单，添加业绩明细
     * $user_id   当前用户id
     * $actual_money   实际支付金额
    */
    public function seckill_add_achievement($user_id,$actual_money){
        // 获取所有上级用户
        $parent_id = array_reverse($this->getPraent($user_id));

        // 开启事务,避免出现垃圾数据
        Db::startTrans();
        try {
            // 判断该用户是否有上级，如果没有上级则不进行循环
            if(count($parent_id) != 0){
                // 如果存在上级
                foreach ($parent_id as $k => $v){
                    // 添加业绩明细
                    $this->add_achievement($v,$user_id,$actual_money);
                }

            }else{
                // 如果没有上级，则直接添加业绩明细

                // 添加业绩明细
                $this->add_achievement("0",$user_id,$actual_money);
            }
            Db::commit();
            return true;
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            Db::rollback();
            return false;
        }

    }

    /**
     * 添加业绩明细
     * $pid 上级用户id
     * $user_id   当前用户id
     * $actual_money   实际支付金额
     */
    protected function add_achievement($pid,$user_id,$actual_money){
        $achievement_data = [
            'pid' => $pid,
            'user_id' => $user_id,
            'money' => $actual_money,
            'createtime' => time(),
        ];
        // 添加业绩
        $i = UserAchievement::insert($achievement_data);
        return $i;
    }

    // 递归获取上级ID
    protected function getPraent($user_id){
        $arr=array();
        // 获取当前用户信息
        $now_userdata = User::where('id',$user_id) -> find();
        // 获取当前用户的父类
        $parent = User::where('id',$now_userdata['p_id']) -> find();

        if($now_userdata['p_id'] > 0){
            $arr[]=$parent['id'];
            $arr=array_merge($this->getPraent($parent['id']),$arr);
        }
        return $arr;
    }



    /**
     * 抢购板块封装的分销方法
     * $user_id 用户id
     * $actual_money   实际支付金额
     *
     */
    public function user_write_off($user_id,$actual_money){
        // 总代理自购分红比率
        $one_own = config('site.seckill_zong_zigou');
        // 代理商自购分红比率
        $two_own = config('site.seckill_dai_zigou');

        // 代理商平级奖分红比率
        $two_pingji = config('site.seckill_dai_pingji');
        // 代理商对总代理上级奖比率
        $dai_zong = config('site.seckill_dai_zong');
        // 消费者对总代理跨级将比率
        $xiao_zong = config('site.seckill_xiao_zong');
        // 消费者对代理商上级奖比率
        $xiao_dai = config('site.seckill_xiao_dai');
        // 消费者对代理商对总代理上上级奖比率
        $xiao_dai_zong = config('site.seckill_xiao_dai_zong');
        if(!$user_id){
            return false;
        }
        if(!$actual_money){
            return false;
        }

        // 获取当前用户信息
        $user_data = User::where('id',$user_id) -> find();

        // 获取所有上级用户
        $parent_id = array_reverse($this->getPraent($user_id));
        /* 分红 start */
        // 判断当前用户等级，消费者无自购分红奖励，代理商自购30%分红奖励，总代理40%分红奖励
        // 如果当前用户是消费者
        if($user_data['level'] == 1){
            // 代理商自购分红
            try {
                // 消费者无自购分红奖励
                // 添加业绩明细
                $this->add_achievement("0",$user_id,$actual_money);

                // 判断该用户是否有上级，如果没有上级则不进行循环
                if(count($parent_id) != 0){
                    // 如果存在上级
                    // 首先循环一次所有上级，查看所有上级中是否同时存在代理商和总代理
                    $num = 0;
                    $zong_id = 0;
                    foreach ($parent_id as $k => $v){
                        // 上级用户数据
                        $shangji_user_data = User::where('id',$v) -> find();
                        // 判断是否有代理商
                        if($shangji_user_data['level'] == 2){
                            // 存在代理商
                            $num = 1;
                        }
                        // 判断是否有总代理
                        if($shangji_user_data['level'] == 3){
                            // 存在总代理
                            $num = 2;
                            $zong_id = $shangji_user_data['id'];
                            break;
                        }
                    }

                    // 循环所有上级
                    foreach ($parent_id as $k => $v){
                        // 上级用户数据
                        $shangji_user_data = User::where('id',$v) -> find();

                        // 判断用户上级是否为总代理
                        if($shangji_user_data['level'] == 3){
                            // 如果上级为总代理，上级享受跨级分红
                            $new_money = round($actual_money * $xiao_zong);
                            $shangji_user_data['bonus'] = $shangji_user_data['bonus'] + $new_money;

                            // 添加记录
                            $this->add_log($shangji_user_data['id'],$user_data['fictitious_id'],$new_money,'佣金','+');
                            $shangji_user_data -> save();
                            // 添加业绩明细
                            $this->add_achievement($shangji_user_data['id'],$user_id,$actual_money);

                            // 上级代理商获得分红之后，结束循环，跳出循环
                            break;
                        }
                        // 判断用户上级是否为代理商
                        if($shangji_user_data['level'] == 2){
                            // 上级为代理商，且无总代理(消费者 -> 代理商)上级奖
                            $new_money = round($actual_money * $xiao_dai);
                            $shangji_user_data['bonus'] = $shangji_user_data['bonus'] + $new_money;

                            // 添加记录
                            $this->add_log($shangji_user_data['id'],$user_data['fictitious_id'],$new_money,'佣金','+');
                            $shangji_user_data -> save();

                            // 添加业绩明细
                            $this->add_achievement($shangji_user_data['id'],$user_id,$actual_money);

                            // 判断用户是否存在总代理
                            if($num == 2){
                                // 用户上级为代理商，并且上上级拥有总代理，则总代理获得1%的分红
                                if($zong_id != 0){
                                    // 查询总代理信息
                                    $zong_data = User::where('id',$zong_id) -> find();
                                    // 上级为代理商，且无总代理(消费者 -> 代理商)上级奖
                                    $new_money = round($actual_money * $xiao_dai_zong);
                                    $zong_data['bonus'] = $zong_data['bonus'] + $new_money;

                                    // 添加记录
                                    $this->add_log($zong_data['id'],$user_data['fictitious_id'],$new_money,'佣金','+');
                                    $zong_data -> save();
                                    // 添加业绩明细
                                    $this->add_achievement($zong_data['id'],$user_id,$actual_money);
                                }
                            }

                            // 获得分红之后，结束循环，跳出循环
                            break;

                        }



                    }
                }

                Db::commit();
            } catch (Exception $e) {
                $this->setError($e->getMessage());
                Db::rollback();
                $this -> error(__('Failure of agent is self purchase bonus'));
            }
        }

        // 如果当前用户是代理商
        if($user_data['level'] == 2){
            // 代理商自购分红
            try {
                // 自购分红金额
                $two_own_money = round($actual_money * $two_own);
                $user_data['bonus'] =  $user_data['bonus']+$two_own_money;

                // 添加记录
                $this->add_log($user_data['id'],$user_data['fictitious_id'],$two_own_money,'自购','+');
                $user_data -> save();


                // 添加业绩明细
                $this->add_achievement("0",$user_id,$actual_money);

                // 判断该用户是否有上级，如果没有上级则不进行循环
                if(count($parent_id) != 0){
                    // 如果存在上级
                    // 首先循环一次所有上级，查看所有上级中是否同时存在代理商和总代理
                    $num = 0;
                    $zong_id = 0;
                    foreach ($parent_id as $k => $v){
                        // 上级用户数据
                        $shangji_user_data = User::where('id',$v) -> find();
                        // 判断是否有代理商
                        if($shangji_user_data['level'] == 2){
                            // 存在代理商
                            $num = 1;
                        }
                        // 判断是否有总代理
                        if($shangji_user_data['level'] == 3){
                            // 存在总代理
                            $num = 2;
                            $zong_id = $shangji_user_data['id'];
                            break;
                        }
                    }
                    // 上级代理商 1%截至（平级奖）
                    // 循环所有上级，如果存在代理商平级奖，则拥有分红1%
                    foreach ($parent_id as $k => $v){
                        // 上级用户数据
                        $shangji_user_data = User::where('id',$v) -> find();
                        // 判断上级用户是否为代理商或者总代理
                        if($shangji_user_data['level'] == 2){
                            // 上级为代理商（平级奖）
                            // 分红金额
                            $shangji_fenhong_money = round($actual_money * $two_pingji);
                            $shangji_user_data['bonus'] = $shangji_user_data['bonus'] + $shangji_fenhong_money;

                            // 添加记录
                            $this->add_log($shangji_user_data['id'],$user_data['fictitious_id'],$shangji_fenhong_money,'佣金','+');
                            $shangji_user_data -> save();

                            // 添加业绩明细
                            $this->add_achievement($shangji_user_data['id'],$user_id,$actual_money);

                            if($num == 2){
                                // 用户上级为代理商，并且上上级拥有总代理，则总代理获得1%的分红
                                if($zong_id != 0){
                                    // 查询总代理信息
                                    $zong_data = User::where('id',$zong_id) -> find();
                                    // 上级为代理商，且无总代理(消费者 -> 代理商)上级奖
                                    $new_money = round($actual_money * $xiao_dai_zong);
                                    $zong_data['bonus'] = $zong_data['bonus'] + $new_money;

                                    // 添加记录
                                    $this->add_log($zong_data['id'],$user_data['fictitious_id'],$new_money,'佣金','+');
                                    $zong_data -> save();

                                    // 添加业绩明细
                                    $this->add_achievement($zong_data['id'],$user_id,$actual_money);
                                }
                            }
                            // 上级代理商获得分红之后，结束循环，跳出循环
                            break;
                        }

                        if($shangji_user_data['level'] == 3){
                            // 上级为总代理（上级）
                            // 分红金额
                            $shangji_fenhong_money = round($actual_money * $dai_zong);
                            $shangji_user_data['bonus'] = $shangji_user_data['bonus'] + $shangji_fenhong_money;
                            $shangji_user_data -> save();

                            // 添加记录
                            $this->add_log($shangji_user_data['id'],$user_data['fictitious_id'],$shangji_fenhong_money,'佣金','+');

                            // 添加业绩明细
                            $this->add_achievement($shangji_user_data['id'],$user_id,$actual_money);
                            // 总代理获得分红之后，结束循环，跳出循环
                            break;
                        }
                    }
                }


                Db::commit();
            } catch (Exception $e) {
                $this->setError($e->getMessage());
                Db::rollback();
                $this -> error(__('Failure of agent is self purchase bonus'));
            }

        }

        // 如果当前用户是总代理
        if($user_data['level'] == 3){
            // 总代理自购分红
            try {
                // 分红金额
                $one_own_money = round($actual_money*$one_own);
                $user_data['bonus'] =  $user_data['bonus']+$one_own_money;


                // 添加记录
                $this->add_log($user_data['id'],$user_data['fictitious_id'],$one_own_money,'自购','+');
                $user_data -> save();

                // 添加业绩明细
                $this->add_achievement("0",$user_id,$actual_money);
                Db::commit();
            } catch (Exception $e) {
                $this->setError($e->getMessage());
                Db::rollback();
                $this -> error(__('Failure of general agent is self purchase bonus'));
            }

        }

        /* 分红 end */

    }

    /**
     * 添加日志
     * $user_id 用户id
     * $other_fictitious_id 对方虚拟id
     * $bonus   变更金额
     * $mome    备注
     * $type    '+'or'-'
     */
    public function add_log($user_id,$other_fictitious_id,$bonus,$mome,$type){
        $user_data = User::where('id',$user_id) -> find();
        $data = [
            'user_id' => $user_id,
            'other_fictitious_id' => $other_fictitious_id,
            'bonus' => $type.$bonus,
            'before' => $user_data['bonus'],
            'memo' => $mome,
            'createtime' => time(),
        ];
        if($type == '+'){
            $data['after'] = $user_data['bonus'] + $bonus;
        }else{
            $data['after'] = $user_data['bonus'] - $bonus;
        }
        UserBonusLog::insert($data);
    }

}

