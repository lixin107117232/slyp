<?php

namespace app\api\validate;

use think\Validate;

class Recommend extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'awardAmount' => 'require|number',
        'id' => 'require|number',
        'awardType' => 'require|number|between:1,2',
    ];



    protected $field = [
    ];
    /**
     * 提示消息
     */
    protected $message = [
    ];
    /**
     * 验证场景
     */
//    protected $scene = [
//        'add'  => [],
//        'edit' => [],
//    ];
//
}
