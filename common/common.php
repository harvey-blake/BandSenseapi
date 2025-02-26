<?php
// 公共函数库

namespace common;

use Db\Db;
use Elliptic\EC;
use kornrunner\Keccak;
use Web3p\EthereumWallet\Wallet;
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


function sendMessage($chat_id, $message, $photoId = null, $videoId = null)
{
    try {
        $token = '7949382682:AAGhPeyqz4ru183scmko8bIjdxp37G3Bs0k';
        $api_url = "https://api.telegram.org/bot$token/";

        // 如果提供了图片 ID，发送图片并附带文本
        if ($photoId) {
            $data = [
                'chat_id' => $chat_id,
                'photo' => $photoId, // 图片的 file_id
                'caption' => $message, // 图片的文本消息
                'parse_mode' => 'HTML', // 如果需要支持 HTML 格式的文本
            ];
            $url = $api_url . 'sendPhoto?' . http_build_query($data);
        }
        // 如果提供了视频 ID，发送视频并附带文本
        elseif ($videoId) {
            $data = [
                'chat_id' => $chat_id,
                'video' => $videoId, // 视频的 file_id
                'caption' => $message, // 视频的文本消息
                'parse_mode' => 'HTML', // 如果需要支持 HTML 格式的文本
            ];
            $url = $api_url . 'sendVideo?' . http_build_query($data);
        } else {
            // 发送纯文本消息
            $data = [
                'chat_id' => $chat_id,
                'text' => $message,
                'parse_mode' => 'HTML', // 设置 HTML 格式
            ];
            $url = $api_url . 'sendMessage?' . http_build_query($data);
        }

        // 发送 GET 请求并获取响应
        $response = file_get_contents($url);
        dump($url); // 可选，输出请求的 URL 以供调试
        // 解析 JSON 响应
        $result = json_decode($response, true);
        dump($result); // 输出 API 响应结果
    } catch (\Throwable $th) {
        // 捕获异常并输出
        echo "发送失败，错误信息: " . $th->getMessage();
    }
}


function sendReplyMessage($chat_id, $message, $message_id, $photoId = null, $videoId = null)
{
    try {
        $token = '7949382682:AAGhPeyqz4ru183scmko8bIjdxp37G3Bs0k';  // 请替换为你的 Bot Token
        $api_url = "https://api.telegram.org/bot$token/";

        // 如果提供了图片 ID，发送图片并附带文本和引用的消息
        if ($photoId) {
            $data = [
                'chat_id' => $chat_id,
                'photo' => $photoId, // 图片的 file_id
                'caption' => $message, // 图片的文本消息
                'reply_to_message_id' => $message_id, // 引用的消息 ID
                'parse_mode' => 'HTML', // 支持 HTML 格式的文本
            ];
            $url = $api_url . 'sendPhoto?' . http_build_query($data);
        }
        // 如果提供了视频 ID，发送视频并附带文本和引用的消息
        elseif ($videoId) {
            $data = [
                'chat_id' => $chat_id,
                'video' => $videoId, // 视频的 file_id
                'caption' => $message, // 视频的文本消息
                'reply_to_message_id' => $message_id, // 引用的消息 ID
                'parse_mode' => 'HTML', // 支持 HTML 格式的文本
            ];
            $url = $api_url . 'sendVideo?' . http_build_query($data);
        } else {
            // 如果没有图片和视频，发送纯文本消息并引用
            $data = [
                'chat_id' => $chat_id,
                'text' => $message,
                'reply_to_message_id' => $message_id, // 引用的消息 ID
                'parse_mode' => 'HTML', // 支持 HTML 格式的文本
            ];
            $url = $api_url . 'sendMessage?' . http_build_query($data);
        }

        // 发送 GET 请求并获取响应
        $response = file_get_contents($url);
        dump($url); // 可选，输出请求的 URL 以供调试
        // 解析 JSON 响应
        $result = json_decode($response, true);
        dump($result); // 输出 API 响应结果
    } catch (\Throwable $th) {
        // 捕获异常并输出
        echo "发送引用消息失败: " . $th->getMessage() . "\n";
    }
}
function mnemonic()
{
    try {
        $wallet = new Wallet();
        $mnemonicLength = 24;
        $wallet->generate($mnemonicLength);
        return ['privateKey' => $wallet->privateKey, 'address' => $wallet->address];
    } catch (\Throwable $th) {
        return false;
    }


    // $wallet->address;
    // danger zone, if the data was leaked, money would be stolen
    // $wallet->privateKey;

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