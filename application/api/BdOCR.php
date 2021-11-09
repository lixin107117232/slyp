<?php

namespace app\api;

use  think\Config;

use think\Log;

class BdOCR{



    //获取百度Access Token
    public  function  getToken(){

  $site['baiduORC_api_key'] = 'L0YOvxwx1cGjkKkypLOUfyut';
  $site['baiduORC_secret_key'] = 'v8DkRRavsQjzTZ5C7Ngd0IrTgCpjiMgX';
        // $site = Config::get("site");
        $param = [];
        $tokenUrl ='https://aip.baidubce.com/oauth/2.0/token';
        $param['grant_type']    = 'client_credentials';//固定参数
        $param['client_id']     = $site['baiduORC_api_key'];
        $param['client_secret'] = $site['baiduORC_secret_key'];


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $oCurl = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($oCurl,true);
        return $data;



    }
    public function request_post($file) {


        $param =[];
        $data = $this->getToken();
        $param['id_card_side'] = 'front';
        $url = 'https://aip.baidubce.com/rest/2.0/ocr/v1/idcard?access_token=' . $data['access_token'];
        $img = file_get_contents(ROOT_PATH .'public'.$file->url);
        $param['image'] = base64_encode($img);


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param);

        $oCurl = curl_exec($ch);
        curl_close($ch);
        $res = json_decode($oCurl,true);
        return  $res;
    }





}