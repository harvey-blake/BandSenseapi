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

function tgverification($data)
{

    // 解析接收到的 URL 编码的数据
    try {
        $botToken = '7949382682:AAGhPeyqz4ru183scmko8bIjdxp37G3Bs0k'; // 替换为你的 Bot Token

        // 解码接收到的URL编码数据
        $decodedString = urldecode($data);

        // 将解码后的数据转换为数组
        parse_str($decodedString, $params);

        // 提取并移除 'hash' 参数
        $receivedHash = $params['hash'];
        unset($params['hash']);

        // 按字母顺序对剩余的参数进行排序
        ksort($params);

        // 生成数据检查字符串，使用换行符分隔
        $dataCheckString = '';
        foreach ($params as $key => $value) {
            $dataCheckString .= "$key=$value\n";
        }
        $dataCheckString = rtrim($dataCheckString); // 移除最后一个换行符

        // 生成 secretKey：将 botToken 作为密钥生成 HMAC 的 secretKey
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);

        // 生成 HMAC-SHA256 的哈希值
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        // 比较哈希值，判断数据是否有效
        if (hash_equals($calculatedHash, $receivedHash)) {
            $params = json_decode($params['user'], true);
            return $params;
        } else {
            echo json_encode(retur('错误', '非法访问', 403));
            exit;
        }
    } catch (\Throwable $th) {
        // 账号未登录
        echo json_encode(retur('错误', '非法访问', 403));
        exit;
    }
}


function sendMessage($chat_id, $message)
{
    try {
        $token = '7949382682:AAGhPeyqz4ru183scmko8bIjdxp37G3Bs0k';
        $api_url = "https://api.telegram.org/bot$token/sendMessage";

        // 创建消息数据
        $data = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML', // 设置 HTML 格式
        ];

        // 使用 http_build_query 编码 URL 参数
        $url = $api_url . '?' . http_build_query($data);

        // 发送 GET 请求并获取响应
        $response = file_get_contents($url);
        dump($url);
        // 解析 JSON 响应
        $result = json_decode($response, true);
        dump($result);
    } catch (\Throwable $th) {
        // 捕获异常并输出
        echo "发送失败，错误信息: " . $th->getMessage();
    }
}

// function sendMessage($chat_id, $message)
// {
//     try {
//         $token = '7949382682:AAGhPeyqz4ru183scmko8bIjdxp37G3Bs0k';
//         $api_url = "https://api.telegram.org/bot$token";

//         // 直接发送 HTML 格式的消息
//         // 确保传递给 Telegram API 的消息内容不包含需要 URL 编码的字符
//         $message = urlencode($message);  // 如果消息里包含 URL，必须使用 urlencode 转义
//         $url = "$api_url/sendMessage?chat_id=$chat_id&text=$message&parse_mode=HTML";

//         // 发送请求
//         file_get_contents($url);
//     } catch (\Throwable $th) {
//         // 捕获异常并输出
//         dump($th);
//     }
// }