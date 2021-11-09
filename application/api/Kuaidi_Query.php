<?php

class Kuaidi_Query
{
    private $_query_url = 'http://poll.kuaidi100.com/poll/query.do';    //实时查询请求地址
//    private $_url = 'http://www.kuaidi100.com/autonumber/auto?num=906919164534&key=IobfFnLz2751';    //实时查询请求地址
    private $_auto_url = 'http://www.kuaidi100.com/autonumber/auto';    //实时查询请求地址
    private $_key = "";
    private $_customer = "";
    private $_params = array();

    public function __construct($num, $com = '', $phone = '', $from = '', $to = '', $resultv2 = 1)
    {
        $site = \think\Config::get("site");
        $this->_key = $site["key"];
        $this->_customer = $site["customer"];
        if (empty($com)) {
            //归属公司智能判断
            $com = $this->check($num);
        }
        $params = array(
            'com' => $com,                    //快递公司编码
            'num' => $num,                    //快递单号
            'phone' => $phone,                //手机号
            'from' => $from,                //出发地城市
            'to' => $to,                    //目的地城市
            'resultv2' => $resultv2        //开启行政区域解析
        );
        $this->_params = $params;
    }

    /**
     * 单号归属公司智能判断接口
     * @param $num
     * @return mixed
     */
    public function check($num)
    {
        $url = $this->_auto_url."?num=".$num."&key=".$this->_key;
        $data = $this->get_curl($url);
        $data_array = json_decode($data, true);
        return $data_array[0]['comCode'];
    }

    /**
     * 执行快递查询接口
     * @return mixed
     */
    public function Query()
    {
        $post_data = array();
        $post_data["customer"] = $this->_customer;
        $post_data["param"] = json_encode($this->_params);
        $sign = md5($post_data["param"] . $this->_key . $post_data["customer"]);
        $post_data["sign"] = strtoupper($sign);
        $params = "";
        foreach ($post_data as $k => $v) {
            $params .= "$k=" . urlencode($v) . "&";        //默认UTF-8编码格式
        }
        $post_data = substr($params, 0, -1);
        $query_data = $this->post_curl($this->_query_url, $post_data);
        return $query_data;
    }

    /**
     * get 请求
     * @param $url
     * @return mixed
     */
    public function get_curl($url) {
        $ch = curl_init();//初始化
        curl_setopt($ch, CURLOPT_URL, $url);//访问的URL
        curl_setopt($ch, CURLOPT_HEADER, false);//设置不需要头信息
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);//只获取页面内容，但不输出
        $result = curl_exec($ch);//执行请求
        curl_close($ch);//关闭curl，释放资源
        $data = str_replace("\"", '"', $result);
        return $result;
    }

    /**
     * post 请求
     * @param $post_data
     * @return mixed
     */
    public function post_curl($url, $post_data)
    {
        //发送post请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        $data = str_replace("\"", '"', $result);
        $data = json_decode($data, true);
        return $data;
    }
}