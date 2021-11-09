<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\exception\UploadException;
use app\common\library\Upload;
use app\common\model\Area;
use app\common\model\Back;
use app\common\model\Version;
use fast\Random;
use think\Config;
use think\Hook;

/**
 * 公共接口
 */
class Common extends Api
{
    protected $noNeedLogin = ['init','agreement','version','about_us','baidu_map',
        'order_overtime','buy_agreement','back_list','new_upload','carousel_time'];
    protected $noNeedRight = '*';

    /**
     * 加载初始化
     *
     * @param string $version 版本号
     * @param string $lng     经度
     * @param string $lat     纬度
     */
    public function init()
    {
        if ($version = input('version')) {
            $lng = input('lng');
            $lat = input('lat');

            //配置信息
            $upload = Config::get('upload');
            //如果非服务端中转模式需要修改为中转
            if ($upload['storage'] != 'local' && isset($upload['uploadmode']) && $upload['uploadmode'] != 'server') {
                //临时修改上传模式为服务端中转
                set_addon_config($upload['storage'], ["uploadmode" => "server"], false);

                $upload = \app\common\model\Config::upload();
                // 上传信息配置后
                Hook::listen("upload_config_init", $upload);

                $upload = Config::set('upload', array_merge(Config::get('upload'), $upload));
            }

            $upload['cdnurl'] = $upload['cdnurl'] ? $upload['cdnurl'] : cdnurl('', true);
            $upload['uploadurl'] = preg_match("/^((?:[a-z]+:)?\/\/)(.*)/i", $upload['uploadurl']) ? $upload['uploadurl'] : url($upload['storage'] == 'local' ? '/api/common/upload' : $upload['uploadurl'], '', false, true);

            $content = [
                'citydata'    => Area::getCityFromLngLat($lng, $lat),
                'versiondata' => Version::check($version),
                'uploaddata'  => $upload,
                'coverdata'   => Config::get("cover"),
            ];
            $this->success('', $content);
        } else {
            $this->error(__('Invalid parameters'));
        }
    }

    /**
     * 上传文件
     * @ApiMethod (POST)
     * @param string $token 用户唯一标识
     * @param File $file 文件流
     */
    public function upload()
    {
        Config::set('default_return_type', 'json');
        //必须设定cdnurl为空,否则cdnurl函数计算错误
        Config::set('upload.cdnurl', '');
        $chunkid = $this->request->post("chunkid");
        if ($chunkid) {
            if (!Config::get('upload.chunking')) {
                $this->error(__('Chunk file disabled'));
            }
            $action = $this->request->post("action");
            $chunkindex = $this->request->post("chunkindex/d");
            $chunkcount = $this->request->post("chunkcount/d");
            $filename = $this->request->post("filename");
            $method = $this->request->method(true);
            if ($action == 'merge') {
                $attachment = null;
                //合并分片文件
                try {
                    $upload = new Upload();
                    $attachment = $upload->merge($chunkid, $chunkcount, $filename);
                } catch (UploadException $e) {
                    $this->error($e->getMessage());
                }
                $this->success(__('Uploaded successful'), ['url' => $attachment->url, 'fullurl' => cdnurl($attachment->url, true)]);
            } elseif ($method == 'clean') {
                //删除冗余的分片文件
                try {
                    $upload = new Upload();
                    $upload->clean($chunkid);
                } catch (UploadException $e) {
                    $this->error($e->getMessage());
                }
                $this->success();
            } else {
                //上传分片文件
                //默认普通上传文件
                $file = $this->request->file('file');
                try {
                    $upload = new Upload($file);
                    $upload->chunk($chunkid, $chunkindex, $chunkcount);
                } catch (UploadException $e) {
                    $this->error($e->getMessage());
                }
                $this->success();
            }
        } else {
            $attachment = null;
            //默认普通上传文件
            $file = $this->request->file('file');
            try {
                $upload = new Upload($file);
                $attachment = $upload->upload();
            } catch (UploadException $e) {
                $this->error($e->getMessage());
            }

            $this->success(__('Uploaded successful'), ['url' => $attachment->url, 'fullurl' => cdnurl($attachment->url, true)]);
        }

    }

    /**
     * 新·文件上传
     * @param File $file 文件流
     *
    */
    public function new_upload(){
        //默认普通上传文件
        $file = $_FILES;
        $type = $_FILES['file']['type'];
        $size = $_FILES['file']['size'];
        $name = $_FILES['file']['name'];
        $tmp_name = $_FILES['file']['tmp_name'];
        $arr = ["image/gif","image/jpeg","image/jpg","image/png"];
        // 判断是否是图片文件
        if(!in_array($type,$arr)){
            $this -> error("请上传图片文件");
        }
        // 判断文件大小   5M
        if($size > 5242880){
            $this -> error("文件大小不能超过5M");
        }
        // 判断文件夹是否存在，如果不存在则生成文件夹
        $m = '/uploads/'.date("Ymd",strtotime('+1 day'))."/";
        $destDir = ROOT_PATH.'public'.$m;
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }
        $new_name = Random::allLower(8).".".explode('/',$type)[1];

        if(move_uploaded_file($tmp_name,$new_name)){
            $this -> success("ok",['url' => $new_name,"fullurl" => cdnurl("/".$new_name, true)]);
        }else{
            $this -> error("图片上传失败，请重试");
        }
        //

    }


    /**
     * 注册协议
     *
     */
    public function agreement()
    {
        //配置信息
        $data=\app\common\model\Config::get(["id"=>18]);
        $this->success('',$data);
    }

    /**
     * 获取版本号
     * @ApiReturn   ({
        'code':'1成功 0失败',
        'msg':'',
        'data':{
            'value':'版本号'
        }
     })
     *
     */
    public function version()
    {
        //配置信息
        $data=\app\common\model\Config::get(["id"=>4]);
        $this->success('',$data);
    }


    /**
     * 关于我们
     *
     * @ApiReturn   ({
        'code':'1成功 0失败',
        'msg':'',
        'data':{
            'value':'关于我们内容'
        }
     })
     */
    public function about_us()
    {
        // 获取关于我们
        $data=\app\common\model\Config::get(["id"=>24]);
        $this->success('',$data);
    }


    /**
     * 获取订单超时时间
     *
     * @ApiReturn   ({
        'code':'1成功 0失败',
        'msg':'',
        'data':{
            'value':'超时时间单位：秒'
        }
     })
     */
    public function order_overtime()
    {
        $data=\app\common\model\Config::get(["id"=>70]);
        $this->success('',$data);
    }

    /**
     * 购买协议
     *@ApiReturn   ({
        'code':'1成功 0失败',
        'msg':'',
        'data':{
            "value": "这是购买协议，请务必遵守！！！",
        }
     })
    */
    public function buy_agreement(){
        $data=\app\common\model\Config::get(["id"=>71]);
        $this->success('',$data);
    }




    /**
     * 银行列表
     *
     * @ApiReturn   ({
        'code':'1成功 0失败 401未登录',
        'msg':'',
        "data":[
            {
                "id": "银行id,
                "name": "银行名称",
                "code": "银行编码",
                "weigh": '权重'
            }
        ]
     })
     */
    public function back_list()
    {
        $back_data = Back::order('weigh','desc')->select();
        $this -> success("查询成功",$back_data);
    }



    /**
     * 轮播图轮播时间
     *
     * @ApiReturn   ({
        'code':'1成功 0失败',
        'msg':'',
        "data":[
            {
                "time": "轮播时间：单位秒,
            }
        ]
     })
     */
    public function carousel_time()
    {
        $back_data = [
            'time' => config('site.carousel_time')
        ];
        $this -> success("查询成功",$back_data);
    }

}
