<?php

namespace app\common\model;

use think\Model;

/**
 * 配置模型
 */
class Config extends Model
{

    // 表名,不含前缀
    protected $name = 'config';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;
    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    // 追加属性
    protected $append = [
        'extend_html'
    ];
    protected $type = [
        'setting' => 'json',
    ];

    /**
     * 读取配置类型
     * @return array
     */
    public static function getTypeList()
    {
        $typeList = [
            'string'        => __('String'),
            'text'          => __('Text'),
            'editor'        => __('Editor'),
            'number'        => __('Number'),
            'date'          => __('Date'),
            'time'          => __('Time'),
            'datetime'      => __('Datetime'),
            'datetimerange' => __('Datetimerange'),
            'select'        => __('Select'),
            'selects'       => __('Selects'),
            'image'         => __('Image'),
            'images'        => __('Images'),
            'file'          => __('File'),
            'files'         => __('Files'),
            'switch'        => __('Switch'),
            'checkbox'      => __('Checkbox'),
            'radio'         => __('Radio'),
            'city'          => __('City'),
            'selectpage'    => __('Selectpage'),
            'selectpages'   => __('Selectpages'),
            'array'         => __('Array'),
            'custom'        => __('Custom'),
        ];
        return $typeList;
    }

    public static function getRegexList()
    {
        $regexList = [
            'required' => '必选',
            'digits'   => '数字',
            'letters'  => '字母',
            'date'     => '日期',
            'time'     => '时间',
            'email'    => '邮箱',
            'url'      => '网址',
            'qq'       => 'QQ号',
            'IDcard'   => '身份证',
            'tel'      => '座机电话',
            'mobile'   => '手机号',
            'zipcode'  => '邮编',
            'chinese'  => '中文',
            'username' => '用户名',
            'password' => '密码'
        ];
        return $regexList;
    }

    public function getExtendHtmlAttr($value, $data)
    {
        $result = preg_replace_callback("/\{([a-zA-Z]+)\}/", function ($matches) use ($data) {
            if (isset($data[$matches[1]])) {
                return $data[$matches[1]];
            }
        }, $data['extend']);
        return $result;
    }

    /**
     * 读取分类分组列表
     * @return array
     */
    public static function getGroupList()
    {
        $groupList = config('site.configgroup');
        foreach ($groupList as $k => &$v) {
            $v = __($v);
        }
        return $groupList;
    }

    public static function getArrayData($data)
    {
        if (!isset($data['value'])) {
            $result = [];
            foreach ($data as $index => $datum) {
                $result['field'][$index] = $datum['key'];
                $result['value'][$index] = $datum['value'];
            }
            $data = $result;
        }
        $fieldarr = $valuearr = [];
        $field = isset($data['field']) ? $data['field'] : (isset($data['key']) ? $data['key'] : []);
        $value = isset($data['value']) ? $data['value'] : [];
        foreach ($field as $m => $n) {
            if ($n != '') {
                $fieldarr[] = $field[$m];
                $valuearr[] = $value[$m];
            }
        }
        return $fieldarr ? array_combine($fieldarr, $valuearr) : [];
    }

    /**
     * 将字符串解析成键值数组
     * @param string $text
     * @return array
     */
    public static function decode($text, $split = "\r\n")
    {
        $content = explode($split, $text);
        $arr = [];
        foreach ($content as $k => $v) {
            if (stripos($v, "|") !== false) {
                $item = explode('|', $v);
                $arr[$item[0]] = $item[1];
            }
        }
        return $arr;
    }

    /**
     * 将键值数组转换为字符串
     * @param array $array
     * @return string
     */
    public static function encode($array, $split = "\r\n")
    {
        $content = '';
        if ($array && is_array($array)) {
            $arr = [];
            foreach ($array as $k => $v) {
                $arr[] = "{$k}|{$v}";
            }
            $content = implode($split, $arr);
        }
        return $content;
    }

    /**
     * 本地上传配置信息
     * @return array
     */
    public static function upload()
    {
        $uploadcfg = config('upload');

        $uploadurl = request()->module() ? $uploadcfg['uploadurl'] : ($uploadcfg['uploadurl'] === 'ajax/upload' ? 'index/' . $uploadcfg['uploadurl'] : $uploadcfg['uploadurl']);

        if (!preg_match("/^((?:[a-z]+:)?\/\/)(.*)/i", $uploadurl) && substr($uploadurl, 0, 1) !== '/') {
            $uploadurl = url($uploadurl, '', false);
        }

        $upload = [
            'cdnurl'    => $uploadcfg['cdnurl'],
            'uploadurl' => $uploadurl,
            'bucket'    => 'local',
            'maxsize'   => $uploadcfg['maxsize'],
            'mimetype'  => $uploadcfg['mimetype'],
            'chunking'  => $uploadcfg['chunking'],
            'chunksize' => $uploadcfg['chunksize'],
            'savekey'   => $uploadcfg['savekey'],
            'multipart' => [],
            'multiple'  => $uploadcfg['multiple'],
            'storage'   => 'local'
        ];
        return $upload;
    }

    /**
     * 刷新配置文件
     */
    public static function refreshFile()
    {
        //如果没有配置权限无法进行修改
        if (!\app\admin\library\Auth::instance()->check('general/config/edit')) {
            return false;
        }
        $config = [];
        $configList = self::all();
        foreach ($configList as $k => $v) {
            $value = $v->toArray();
            if (in_array($value['type'], ['selects', 'checkbox', 'images', 'files'])) {
                $value['value'] = explode(',', $value['value']);
            }
            if ($value['type'] == 'array') {
                $value['value'] = (array)json_decode($value['value'], true);
            }
            $config[$value['name']] = $value['value'];
        }
        file_put_contents(
            CONF_PATH . 'extra' . DS . 'site.php',
            '<?php' . "\n\nreturn " . var_export_short($config) . ";\n"
        );
        return true;
    }

    /**
     * 获取注册协议
     * */
    public function getAgreement(){
        //配置信息
        $data=\app\common\model\Config::get(["id"=>18]);
        return $data;
    }

    /**
     * 获取短信接入URL
    */
    public function getSmsURL(){
        return config('site.url');
    }

    /**
     * 获取应用APP_KEY
    */
    public function getSmsAppKey(){
        return config('site.APP_KEY');
    }

    /**
     * 获取应用APP_Secret
    */
    public function getSmsAppSecret(){
        return config('site.APP_Secret');
    }

    /**
     * 获取签名通道
    */
    public function getSmsSignatureChannel(){
        return config('site.SignatureChannel');
    }

    /**
     * 获取注册模板ID
    */
    public function getSmsRegistTemplate(){
        return config('site.regist_template');
    }

    /**
     * 获取登录模板ID
    */
    public function getSmsLoginTemplate(){
        return config('site.login_template');
    }

    /**
     * 获取修改手机号模板ID
    */
    public function getSmsUpdataPhoneTemplate(){
        return config('site.updata_phone_template');
    }

    /**
     * 获取修改密码模板ID
    */
    public function getSmsUpdatePwdTemplate(){
        return config('site.update_pwd_template');
    }

    /**
     * 获取修改支付密码模板ID
    */
    public function getSmsUpdatePayPwdTemplate(){
        return config('site.update_pay_pwd_template');
    }

    /**
     * 获取通用模板ID
    */
    public function getSmsGlobalTemplate(){
        return config('site.global_template');
    }

    /**
     * 获取医美-总代理自购奖比率
    */
    public function getHospitalZongZigou(){
        return config('site.hospital_zong_zigou');
    }

    /**
     * 获取医美-代理商自购奖比率
    */
    public function getHospitalDaiZigou(){
        return config('site.hospital_dai_zigou');
    }

    /**
     * 获取医美-代理商平级奖比率
    */
    public function getHospitalDaiPingji(){
        return config('site.hospital_dai_pingji');
    }

    /**
     * 获取医美-代对总上级奖比率
    */
    public function getHospitalDaiZong(){
        return config('site.hospital_dai_zong');
    }

    /**
     * 获取医美-消对代上级奖比率
    */
    public function getHospitalXiaoDai(){
        return config('site.hospital_xiao_dai');
    }

    /**
     * 获取医美-消对总上级奖比率
    */
    public function getHospitalXiaoZong(){
        return config('site.getHospitalXiaoZong');
    }

    /**
     * 获取医美-消代总跨级奖比率
    */
    public function getHospitalXiaoDaiZong(){
        return config('site.hospital_xiao_dai_zong');
    }
}
