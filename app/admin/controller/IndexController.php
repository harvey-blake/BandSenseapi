<?php

// 默认控制器 Index

// 命名空间与类文件所在的路径对应映射
namespace app\admin\controller;

use function common\dump;

// 用户自定义控制器, 应该继承自基类控制器

class IndexController extends BaseController
{

    // 默认方法
    // 需要几个参数  就接收几个参数 多余的可以不用管
    public function index($params1, $params2)
    {
        echo $params1;
    }
}
