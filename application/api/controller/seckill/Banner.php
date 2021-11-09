<?php

namespace app\api\controller\seckill;

use app\common\controller\Api;
use app\common\model\seckill\Banner as BannerModel;

/**
 * 秒杀banner接口
 */
class Banner extends Api
{
    protected $noNeedLogin = ['index'];
    protected $noNeedRight = ['*'];
    /**
     * 所有banner
     */
    public function index()
    {
        $list=BannerModel::order("weigh desc,createtime desc")->select();
        $this->success('返回成功', $list);
    }

}
