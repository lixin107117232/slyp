<?php

namespace app\api\controller;

use addons\epay\library\Service;
use app\admin\controller\hospital\Goods;
use app\admin\model\HospitalSku;
use app\admin\model\Shop;
use app\common\controller\Api;
use app\common\model\hospital\Order;
use Symfony\Component\HttpFoundation\Request;
use think\Db;
use think\Exception;

/**
 * 医美接口
 */
class Hospital extends Api
{
    protected $noNeedLogin = ['query_banner','class_goods_data','hot_search','type_image','goods_search','goods_data','goods_class','goods_details','goods_spec_attr','goods_price_select','returnx','notifyx'];
    protected $noNeedRight = ['*'];

    /**
     * 轮播图
     * @ApiReturn   ({
        'code':'1查询成功 401未登录',
        'msg':''
        'data' :{
            // 只显示五条数据
            {
                "id": "轮播图ID",
                "image": "轮播大图",
                "status": "跳转方式:0=不跳转,1=商品,2=分类",
                "status_id": "跳转商品/分类id",
            }
        }
    })
     */
    public function query_banner()
    {
        // 获取轮播图
        $banner_data = $this->auth->query_banner();
        $this->success('请求成功',$banner_data);
    }


    /**
     * 医美商品分类
     * @ApiReturn   ({
        'code':'1查询成功 401未登录',
        'msg':''
        'data' :{
            {
                "id": "分类ID",
                "name": "分类名称",
                "url": "分类图片路径",
            }
        }
    })
     */
    public function goods_class()
    {
        // 获取商品分类
        $goods_class = $this->auth->goods_class();
        $this->success('请求成功',$goods_class);
    }


    /**
     * 医美商品分类主图
     * @ApiReturn   ({
        'code':'1查询成功',
        'msg':'',
        "data": "图片链接"
    })
     */
    public function type_image()
    {
        // 获取商品分类主图
        $goods_class = $this->auth->type_image();
        $this->success('请求成功',$goods_class);
    }



    /**
     * 商品列表
     * @param int $page 页数,默认为:1,每页5条数据（测试数据4条，每页2条）
     * @param int $type_id 分类ID,默认推荐分类
     * @ApiReturn   ({
        'code':'1查询成功 401未登录',
        'msg':'',
        'data' :[
            {
                "goods_id": "商品ID",
                "type_id": "分类ID",
                "status": "状态:0=展示中,1=已兑完,2=仓库中",
                "goodsdata": {
                    "name": "商品名称",
                    "cover_image": "商品封面图",
                    "stock": "库存",
                    "sales": "价格",
                },
                "typedata": {
                    "name": "类别名称"
                },
                "weigh": "权重",
            }
        ]
     })
     */
    public function goods_data()
    {
        $page = input('page');
        $type_id = input('type_id');
        if(!$page){
            $page = 1;
        }
        // 获取商品信息
        $goods_data = $this->auth->goods_data($page,$type_id);
        $this->success('请求成功',$goods_data);
    }


    /**
     * 分类列表
     * @param int $type_id 分类ID,默认推荐分类
     * @ApiReturn   ({
        'code':'1查询成功 401未登录',
        'msg':'',
        'data' :[
            {
                "goods_id": "商品ID",
                "type_id": "分类ID",
                "status": "状态:0=展示中,1=已兑完,2=仓库中",
                "goodsdata": {
                    "name": "商品名称",
                    "cover_image": "商品封面图",
                    "stock": "库存",
                    "sales": "价格",
                },
                "typedata": {
                    "name": "类别名称"
                },
                "weigh": "权重",
            }
        ]
     })
     */
    public function class_goods_data()
    {
        $type_id = input('type_id');
        // 获取商品信息
        $goods_data = $this->auth->class_goods_data($type_id);
        $this->success('请求成功',$goods_data);
    }


    /**
     * 搜索界面-热门搜索
     * @ApiReturn   ({
        'code':'1查询成功 0查询失败',
        'msg':''
        'data' :[
            {
                "id": "搜索ID",
                "name": "商品名称"
            }
        ]
     })
     */
    public function hot_search()
    {
        // 获取商品信息
        $data = $this->auth->hot_search();
        $this->success('请求成功',$data);
    }


    /**
     * 搜索界面-最近搜索
     * @param string $token 用户唯一标识
     * @ApiReturn   ({
        'code':'1查询成功 401未登录',
        'msg':''
        'data' :[
            {
                "id": "搜索ID",
                "name": "商品名称"
            }
        ]
     })
     */
    public function recent_search()
    {
        // 获取商品信息
        $data = $this->auth->recent_search();
        $this->success('请求成功',$data);
    }


    /**
     * 确认搜索功能
     * @param string $value 搜索内容
     * @param int $page 页数,默认为:1,每页10条数据
     * @param string $order 排序：(价格：sales空格desc降序，sales空格asc升序)(销量：stock空格desc降序，stock空格asc升序)
     * @ApiReturn   ({
        'code':'1查询成功',
        'msg':'',
        'data' :[
            {
                "goods_id": "商品ID",
                "type_id": "分类ID",
                "type_id": "分类ID",
                "status": "状态:0=展示中,1=已兑完,2=仓库中",
                "stock": "库存",
                "sales": "价格",
                "goodsdata": {
                    "name": "商品名称",
                    "cover_image": "商品封面图",
                    "stock": "库存",
                    "sales": "价格",
                },
                "typedata": {
                    "name": "类别名称"
                },
                "weigh": "权重",
            }
        ]
     })
     */
    public function goods_search()
    {
        $value = input('value');
        $page = input('page');
        $order = input('order');
        if(!$page){
            $page = 1;
        }
        if(!$order){
            $order = "sales";
        }
        // 获取商品信息
        $data = $this->auth->goods_search($value,$page,$order);
        $this->success('请求成功',$data);
    }


    /**
     * 商品详情
     * @param int $goods_id 商品ID
     * @ApiReturn   ({
        'code':'1查询成功',
        'msg':''
        'data' :{
            "name": "商品名",
            "cover_image": "商品主图",
            "images": "商品图",
            "stock": "库存",
            "sales_min": "最低价",
            "sales_max": "最高价",
            "shop_id": "店铺ID",
            "shop_name": "店铺名称"
         }
     })
     */
    public function goods_details()
    {
        $goods_id = input('goods_id');
        if(!$goods_id){
            $this -> error("请上传商品ID","goods_id");
        }
        // 验证商品是否存在
        if(!\app\admin\model\Goods::where('id',$goods_id) -> find()){
            $this -> error("商品不存在");
        }
        // 获取商品信息
        $data = $this->auth->goods_details($goods_id);
        $this->success('请求成功',$data);
    }


    /**
     * 商品规格与属性
     * @param int $goods_id 商品ID
     * @ApiReturn   ({
        'code':'1查询成功',
        'msg':''
        'data' :[
            {
                // 规格
                "attr_key_id": "规格ID",
                "attr_name": "规格名称",
                // 属性
                "attr": [
                    {
                        "symbol": "属性ID",
                        "attr_key_id": "关联的规格ID",
                        "attr_value": "属性名称"
                    }
                ]
            }
        ]
     })
     */
    public function goods_spec_attr()
    {
        $goods_id = input('goods_id');
        if(!$goods_id){
            $this -> error("请上传商品ID","goods_id");
        }
        // 验证商品是否存在
        if(!\app\admin\model\Goods::where('id',$goods_id) -> find()){
            $this -> error("商品不存在");
        }
        // 获取商品信息
        $data = $this->auth->goods_spec_attr($goods_id);
        $this->success('请求成功',$data);
    }


    /**
     * 规格属性组合价格查询
     * @param int $goods_id 商品ID
     * @param string $string 属性值,按以小到大顺序组合成的字符串，用英文逗号(,)隔开
     * @ApiReturn   ({
        'code':'1查询成功',
        'msg':''
        'data' :{
            "sku_id": "属性价格ID",
            "stock": "库存",
            "sales": "价格"
        }
     })
     */
    public function goods_price_select()
    {
        $goods_id = input('goods_id');
        $string = input('string');
        if(!$goods_id){
            $this -> error("请上传商品ID","goods_id");
        }
        // 验证商品是否存在
        if(!\app\admin\model\Goods::where('id',$goods_id) -> find()){
            $this -> error("商品不存在");
        }
        if(!$string){
            $this -> error("请提交属性值","string");
        }
        // 将空格、换行符、中文逗号、小数点等替换成英文逗号
        $string = preg_replace("/(\n)|(\s)|(\t)|(\')|(')|(，)|(\.)/",',',$string);
        // 验证属性值是否存在
        if(!HospitalSku::where('attr_symbol_path' , $string) -> find()){
            $this -> error("当前属性值不存在，请重新提交","string");
        }
        // 获取商品信息
        $data = $this->auth->goods_price_select($goods_id,$string);
        if(!empty($data)){
            $this->success('请求成功',$data);
        }else{
            $this->success('未查询到数据',$data);
        }

    }


    /**
     * 创建医美订单
     * @param string $token 用户唯一标识
     * @param int $goods_id 商品ID
     * @param int $sku_id 属性价格ID,非必填
     * @param int $num 数量
     * @ApiReturn   ({
        'code':'1成功 3未实名 4未设置支付密码 401未登录',
        'msg':'',
        'data' :{
            "order_no": "订单编号",
            "all_money": "订单总金额",
        }
     })
     */
    public function create_hospital_order()
    {
        $user = $this -> auth -> getUser();
        $goods_id = input('goods_id');
        $sku_id = input('sku_id');
        $num = input('num');
        if(!$goods_id){
            $this -> error("请提交商品ID","goods_id");
        }
        if(!$num){
            $this -> error("请提交购买数量","num");
        }
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
        // 创建订单的时候，不需要判断用户是否设置支付密码
//        if(!$user -> pay_pwd){
//            $pay_pwd_data = [
//                "code"=> 4,
//                "msg"=> "用户未设置支付密码，即将跳转到设置支付密码",
//                "time"=> time(),
//                "data"=> null
//            ];
//            return \GuzzleHttp\json_encode($pay_pwd_data,JSON_UNESCAPED_UNICODE);
//        }
        $hos_data =\app\admin\model\Goods::where('id',$goods_id) -> find();
        // 验证商品是否存在
        if(!$hos_data){
            $this -> error("商品不存在");
        }
        // 判断商品是否选择规格:0=否,1=是
        if($hos_data['specs_data'] == 1){
            if(!$sku_id){
                $this -> error("请提交属性价格ID","sku_id");
            }
            // 如果提交属性价格id，则通过sku表判断库存
            $sku_data = HospitalSku::where(['sku_id' => $sku_id,'item_id' => $goods_id]) -> find();
            if(!$sku_data){
                $this -> error("当前属性值不存在，请重新提交","sku_id");
            }
            // 验证属性是否还有库存
            if($sku_data['stock'] == 0){
                $this -> error("对不起当前商品已售罄");
            }
            if($sku_data['stock'] < $num){
                $this -> error("对不起当前商品库存不足");
            }
        }else{
            // 如果商品无规格，则通过hospital_goods表判断库存
            if($hos_data['stock'] == 0){
                $this -> error("对不起当前商品已售罄");
            }
            if($hos_data['stock'] < $num){
                $this -> error("对不起当前商品库存不足");
            }
        }

        // 创建医美订单
        $data = $this->auth->create_hospital_order($goods_id,$sku_id,$num);

        if($data){
            $this->success('订单添加成功',$data);
        }else{
            $this->success('订单添加失败',$data);
        }
    }


    /**
     * 查询医美订单
     * @param string $token 用户唯一标识
     * @param int $order_no 订单编号
     * @ApiReturn   ({
        'code':'1成功 401未登录',
        'msg':'',
        'data' :{
            "id":4,
            "order_no":"订单编号",
            "user_id":"用户id",
            "hospital_goods_id":"商品id",
            "hospital_goods_name":"商品名称",
            "sku_str":"商品属性值",
            "one_money":"单价",
            "all_money":"总价",
            "actual_money":"实际支付价格",
            "num":"购买数量",
            "status":"状态：0待支付，1待使用，2已完成，3已取消",
            "pay_status":"支付方式:0=未支付1=余额,2=微信,3=支付宝",
            // 商品信息
            "goods_data":
                {
                    "name":"商品名称",
                    "cover_image":"商品主图"
            },
            // 店铺信息
            "shop_name": {
                "shop_id": "店铺id"
                "shop_name": "店铺名称"
            },
            "user_pay_status": 用户是否设置支付密码:1以设置0未设置
        }
     })
     */
    public function select_hospital_order()
    {
        $user = $this -> auth -> getUser();
        $order_no = input('order_no');
        if(!$order_no){
            $this -> error("请上传订单编号","order_no");
        }
        $order_data = \app\admin\model\Order::where('order_no',$order_no)-> where('user_id',$user['id'])-> find();
        // 验证订单是否存在
        if(!$order_data){
            $this -> error("订单不存在");
        }
        // 查询医美商品信息
        $hospital_goods_data = \app\common\model\hospital\Goods::where('id',$order_data['hospital_goods_id']) -> find();
        // 查询商品信息
        $goods_data = \app\common\model\Goods::where('id',$hospital_goods_data['goods_id']) -> find();
        $order_data['goods_data'] = [
            'name' => $goods_data['name'],
            'cover_image' => $goods_data['cover_image'],
        ];
        // 查询店铺信息
        $shop_data = Shop::where('id',$hospital_goods_data['shop_id']) -> find();
        $order_data['shop_name'] = [
            'shop_id' => $shop_data['id'],
            'shop_name' => $shop_data['name'],
        ];
        $order_data['user_pay_status'] = $user['pay_pwd'] ? 1 : 0;
        if($order_data){
            $this->success('查询成功',$order_data);
        }else{
            $this->success('查询失败',$order_data);
        }

    }


    /**
     * 支付医美订单
     * @param string $token 用户唯一标识
     * @param string $order_id 订单id
     * @param string $order_no 订单编号
     * @param float $actual_money 订单支付金额
     * @param int $pay_method 支付方式:1=余额,2=微信,3=支付宝
     * @param int $pay_pwd 支付密码,只有余额支付需要支付密码
     * @ApiReturn   ({
        'code':'1成功 3未实名 4未设置支付密码 401未登录',
        'msg':'',
        'data' :{
            "list": "回调数据",
        }
     })
     */
    public function pay_hospital_order()
    {
        $user = $this -> auth -> getUser();
        $order_id = input('id');
        $order_no = input('order_no');
        $actual_money = input('actual_money');
        $pay_method = input('pay_method');
        $pay_pwd = input('pay_pwd');
        if(!$order_id){
            $this -> error("请提交订单id","order_id");
        }
        if(!$order_no){
            $this -> error("请提交订单编号","order_no");
        }
        if(!$actual_money){
            $this -> error("请提交订单支付金额","all_money");
        }
        if(!$pay_method){
            $this -> error("请选择支付方式","pay_method");
        }
        // 判断用户是否实名
        if($user -> real_status == 0){
            $real_status_data = [
                "code"=> 3,
                "msg"=> "用户未实名",
                "time"=> time(),
                "data"=> null
            ];
            return \GuzzleHttp\json_encode($real_status_data,JSON_UNESCAPED_UNICODE);
        }
        // 查询订单
        $order_data = Order::where('id',$order_id) -> find();
        // 验证订单是否存在
        if(!$order_data){
            $this -> error("订单不存在");
        }
        // 余额支付
        if($pay_method == 1){
            // 如果用户选择余额支付，则需要判断用户是否设置支付密码
            if(!$user -> pay_pwd){
                $pay_pwd_data = [
                    "code"=> 4,
                    "msg"=> "用户未设置支付密码",
                    "time"=> time(),
                    "data"=> null
                ];
                return \GuzzleHttp\json_encode($pay_pwd_data,JSON_UNESCAPED_UNICODE);
            }
            // 如果支付方式为余额支付,需要输入支付密码
            if(!$pay_pwd){
                $this -> error("请输入支付密码","pay_pwd");
            }
            // 如果支付方式为余额支付，则判断用户余额是否足够
            if($user -> money < $actual_money){
                $this -> error("用户余额不足");
            }

            // 支付医美订单
            $data = $this->auth->pay_hospital_order($order_id,$actual_money,$pay_method,$pay_pwd);
            if($data){
                $this->success('接口调用成功',$data);
            }else{
                $this->error('接口调用失败',$data);
            }
        }
        // 微信支付
        if($pay_method == 2){
            $this->error('微信支付开发中');
        }
        // 支付宝支付
        if($pay_method == 3){
            // 支付类型 支付宝：'alipay',微信： 'wechat'
            $type = "alipay";
            // 生成支付宝回调
            $params = [
//                'amount' => $order_data['actual_money'],
                'amount' => 0.01,
                'out_trade_no' => $order_no,
                'type' => $type,
                'title' => '购买'.$order_data['hospital_goods_name'],
                'notifyurl' => $this -> request -> domain() .
                    '/api/hospital/notifyx/paytype/'.$type.
                    '/order_id/'.$order_id.
                    '/actual_money/'.$order_data['actual_money'].
                    '/pay_method/'.$pay_method,
                'returnurl' => $this -> request -> domain() . '/api/hospital/returnx/paytype/'.$type,
                'method' => "app"
            ];
            $response=Service::submitOrder($params);
            $data['list'] = $response;
            $this->success('接口调用成功',$data);
        }
    }

    /*
     * 支付成功回调
     */
    public function notifyx(){
        $paytype = $this->request->param('paytype');

        $order_id = $this->request->param('order_id');
        $actual_money = $this->request->param('actual_money');
        $pay_method = $this->request->param('pay_method');

//        $pay = Service::checkNotify($paytype);
//
//        if (!$pay) {
//            $this->error('签名错误');
//        }
//        $data = $pay->verify();
        $request = Request::createFromGlobals();
        $data = $request->request->count() > 0 ? $request->request->all() : $request->query->all();

        Db::startTrans();
        try {
            // 如果用户选择的微信或者支付宝支付，则将订单号修改为支付宝或者微信的订单号。
            Order::where('id',$order_id) -> update(['order_no' => $data['out_trade_no']]);
            // 支付医美订单
            $data = $this->auth->pay_hospital_order($order_id,$data['total_amount'],$pay_method);
            Db::commit();
            if($data){
//                echo "success";
                $this->success('接口调用成功',$data);
            }else{
                $this->error('接口调用失败',$data);
            }
        } catch (Exception $e) {
            Db::rollback();
            $this->error('接口调用失败',$data);
        }
    }

    /*
     * 支付成功返回
     */
    public function returnx()
    {
        $type = $this->request->param('paytype');
        if (Service::checkReturn($type)) {
            $this->error('签名错误');
        }

        //你可以在这里定义你的提示信息,但切记不可在此编写逻辑
        $this->success("恭喜你！支付成功!");

        return;
    }


    /**
     * 查看医美订单
     * @param string $token 用户唯一标识
     * @param int $status 订单状态：0待支付,1待使用,2已完成,3已取消，不提交则查询全部
     * @param int $page 页数,默认为:1,每页5条数据
     * @ApiReturn   ({
        'code':'1成功 401未登录',
        'msg':''
        'data' :[
            {
            "id": "订单ID",
            "order_no": "订单编号",
            "hospital_goods_name": "医美商品名称",
            "sku_str": "购买属性名称",
            "one_money": "单价",
            "num": "购买数量",
            "all_money": "总金额",
            "actual_money": "实际支付金额",
            "status": "订单状态：0待支付，1待使用，2已完成，3已取消",
            "pay_status": "支付方式:1=余额,2=微信,3=支付宝",
            "createtime": "创建时间",
            "over_time" : "订单过期时间",
            "over_status" : "订单是否过期：1已过期0未过期"
            }
        ]
     })
     */
    public function see_hospital_order(){
        $page = input('page');
        $status = input('status');
        if(!$page){
            $page = "1";
        }
        // 查询用户医美订单
        $data = $this -> auth -> see_hospital_order($status,$page);

        $this -> success('接口调用成功',$data);
    }


    /**
     * 取消医美订单
     * @param string $token 用户唯一标识
     * @param int $order_id 订单id
     * @ApiReturn   ({
        'code':'1成功 401未登录',
        'msg':''
        'data' :
     })
     */
    public function cancel_hospital_order(){
        $order_id = input('order_id');
        if(!$order_id){
            $this->error(__('No Results were found'));
        }
        $order_data = \app\admin\model\Order::where('id',$order_id) -> find();
        if(empty($order_data)){
            $this->error(__('订单不存在'));
        }
        // 取消医美订单
        $order_data -> status = 3;
        $i = $order_data -> save();
        if($i){
            $this -> success('接口调用成功','订单取消成功');
        }else{
            $this->error('接口调用失败');
        }

    }


    /**
     * 去使用医美订单
     * @param string $token 用户唯一标识
     * @param int $order_id 订单id
     * @ApiReturn   ({
        'code':'1成功 401未登录',
        'msg':''
        'data' :{
            "id": "订单ID",
            "order_no": "订单编号",
            "hospital_goods_name": "医美商品名称",
            "sku_str": "购买属性名称",
            "one_money": "单价",
            "num": "购买数量",
            "all_money": "总金额",
            "actual_money": "实际支付金额",
            "status": "订单状态：0待支付，1待使用，2已完成",
            "pay_status": "支付方式:1=余额,2=微信,3=支付宝",
            "createtime": "创建时间",
            "paytime": "支付时间",
            "over_time": "订单过期时间",
            "over_status": "订单是否过期：1已过期0未过期",
            // 店铺信息
            "shop_data": {
                "id": 4,
                "name": "店铺名称",
                "phone": "店铺联系人电话",
                "location": "店铺详细地址",
                "longitude": "经度",
                "latitude": "纬度",
            }
        }
     })
     */
    public function use_hospital_order(){
        $order_id = input('order_id');
        if(!$order_id){
            $this->error(__('No Results were found'));
        }
        $order_data = \app\admin\model\Order::where('id',$order_id) -> find();
        if(empty($order_data)){
            $this->error(__('订单不存在'));
        }

        $data = use_hospital_order($order_id);
        if($data){$this -> auth ->
        $this -> success('接口调用成功',$data);
        }else{
            $this->error('接口调用失败');
        }

    }

    /**
     * 抢购订单，添加业绩明细
     * $pid 上级用户id
     * $user_id   当前用户id
     * $actual_money   实际支付金额
     * */
    protected function add_achievement(){
        $user_id = $this -> auth -> getUser()['id'];

        $actual_money = "100";
        $data = $this -> auth -> seckill_add_achievement($user_id,$actual_money);
        if($data){
            $this -> success("ok",$data);
        }else{
            $this -> error("no",$data);
        }

    }

}
