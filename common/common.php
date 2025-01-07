<?php
// 公共函数库

namespace common;

use Db\Db;
use Elliptic\EC;
use kornrunner\Keccak;
// 快捷打印
function dump(...$data)
{
    foreach ($data as $item) {
        // true: 只返回不打印
        $result  =  var_export($item, true);
        // 自定义变量显示样式
        $style = 'border:1px solid #ccc;border-radius:5px;';
        $style .= 'background: #efefef; padding: 8px;';
        // 格化式打印
        printf('<pre style="%s">%s</pre>', $style, $result);
    }
}


// 返回参数 就是用的比较多的
function retur($massage = '', $data = '', $code = '')
{   // 什么都不传 表示成功   但是不返回CODE以外的数据
    // data 为返回的集 ms为返回的消息
    if ($code == '') {
        return   ['code' => 200, 'data' => $data, 'massage' => $massage, 'state' => 'ok'];
    } else {
        return    ['code' => $code, 'data' => $data, 'massage' => $massage, 'state' => 'error'];
    }
}
