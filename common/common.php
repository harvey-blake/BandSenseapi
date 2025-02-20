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
//加密
function encryptData($data)
{
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt(json_encode($data), 'aes-256-cbc', '93a1c3a4b6e9f0d1e4b0f78a9cd7b0d1a78d9b0e4b0e', 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}
// 解密
function decryptData($encryptedData)
{
    list($encryptedData, $iv) = explode('::', base64_decode($encryptedData), 2);
    return json_decode(openssl_decrypt($encryptedData, 'aes-256-cbc', '93a1c3a4b6e9f0d1e4b0f78a9cd7b0d1a78d9b0e4b0e', 0, $iv), true);
}
//精度处理
function truncateToPrecision($value, $precision)
{
    $factor = pow(10, $precision);
    return floor($value * $factor) / $factor;
}
