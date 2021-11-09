<?php

namespace app\api\controller\integral;

use app\common\controller\Api;
use app\common\model\integral\Goods as GoodsModel;
use app\common\model\integral\Type as TypeModel;
use think\Db;

/**
 * 积分商城搜索接口
 */
class Search extends Api
{
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = [ "index",'goods'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];
    /*
     * 获取用户搜索列表
     * 获取用户搜索列表
     * */
    public function index()
    {
        $user = $this->auth->getUser();
        //$user["id"]=1;
        $list=Db::name("integral_search")->field("name")
            ->where(["user_id"=>$user["id"]])->order("frequency desc,createtime desc")
            ->limit(0,10)
            ->select();
        $hot_list=Db::name("integral_search")
            ->field("COUNT(*) num,SUM(frequency) frequency,name")
            ->group("name")
            ->order("frequency desc,num desc")
            ->limit(0,10)
            ->select();
        $this->success('返回成功', ["list"=>$list,"hot_list"=>$hot_list]);
    }
    /*
     * 搜索商品
     * */
    public function goods(){
        $user = $this->auth->getUser();
        //$user["id"]=1;
        $page = !$this->request->param('page')?1:$this->request->param('page');//页
        $param =$this->request->param();
        if(!$this->request->param("name"))$this->error("搜索词为空",'',2);
        if(isset($param["order"]))
        {
            $order=$param["order"];
        }else
        {
            $order="weigh desc,createtime desc";
        }
        $list=GoodsModel::with(["allgoods"=>function($query){
            $query->withField('id,name,cover_image')->where("name","like","%".$this->request->param("name")."%");//->order("weigh desc,createtime desc")
        }])->order($order)
            ->paginate(10,'',['page' => $page, 'list_rows' => 10]);
//        if($user["id"]){
//            $search=Db::name("integral_search")
//                ->where(["user_id"=>$user["id"],"name"=>$param["name"]])
//                ->find();
//            if($search)
//            {
//                Db::name("integral_search")->where(["id"=>$search["id"]])->setInc('frequency',1);//搜索表增加次数
//            }else
//            {
//                Db::name("integral_search")
//                    ->insert([
//                        "user_id"=>$user["id"],
//                        "name"=>$param["name"],
//                        "frequency"=>1,
//                        "createtime"=>time(),
//                    ]);//搜索表新增数据
//            }
//        }

        $this->success('返回成功', $list);
    }

}
