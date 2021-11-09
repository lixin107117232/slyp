<?php

namespace app\api\controller\integral;

use app\common\controller\Api;
use app\common\model\integral\Banner as BannerModel;

/**
 * 积分商城banner接口
 */
class Banner extends Api
{

    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['index', 'test1'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['test2'];
    /**
     * 所有banner
     */
    public function index()
    {
        $list=BannerModel::order("weigh desc,createtime desc")->select();
        $this->success('返回成功', $list);
    }

}
