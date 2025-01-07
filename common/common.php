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

function tgverification($data)
{

    // 解析接收到的 URL 编码的数据

    $botToken = '7643239681:AAGMO59IIDDzMqZ5SLi2mFnFDTi0bXLrMPY'; // 替换为你的 Bot Token

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
        return false;
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

// ETH秘钥解析
function ETHverifyMessage($message, $signature)
{
    try {
        $msglen = strlen($message);
        $hash   = Keccak::hash("\x19Ethereum Signed Message:\n{$msglen}{$message}", 256);
        $sign   = [
            "r" => substr($signature, 2, 64),
            "s" => substr($signature, 66, 64)
        ];
        $recid  = ord(hex2bin(substr($signature, 130, 2))) - 27;
        if ($recid != ($recid & 1))
            return false;
        $ec = new EC('secp256k1');
        $pubkey = $ec->recoverPubKey($hash, $sign, $recid);
        return   "0x" . substr(Keccak::hash(substr(hex2bin($pubkey->encode("hex")), 1), 256), 24);
    } catch (\Throwable $th) {
        return false;
    }
}
// 邮件发送


// 用户登录
// 测试查询数据库
function chaxun()
{

    $user =  Db::table('dex_user')->field('*')->where(['id' => 123])->find();
    dump($user);
    $arr =  Db::table('dex_user')->where(['id' => 61])->update(['email' => '3@q.com']);
    dump($arr);
    $arr =  Db::table('dex_secretKey1')->insert(['user' => '$username', 'id' => '$uniqid', 'secretKey' => '$token']);
    dump($arr);
    $arr =  Db::table('dex_user')->field('id')->where(['address' => '0xc274c98d8db463bd520ac8066f2954aed569ac72'])->find();
    dump($arr);
}

// 公共函数可以当中间件吗？
// 用户登录  用户

// 加密
function encryptData($data)
{
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt(json_encode($data), 'aes-256-cbc', SECRETKEY, 0, $iv);
    // 存入 给个ID就可以了  前端发送的单纯就是一个ID
    return base64_encode($encrypted . '::' . $iv);
}
// 解密
function decryptData($encryptedData)
{
    list($encryptedData, $iv) = explode('::', base64_decode($encryptedData), 2);
    return json_decode(openssl_decrypt($encryptedData, 'aes-256-cbc', SECRETKEY, 0, $iv), true);
}
// 发送电报消息
function Message($chat_id)
{
    $welcome_message = "KittyToken is a new blockchain token designed to offer innovation and support for blockchain developers and enthusiasts. Combining cutting-edge technology with feline inspiration, our token brings more fun and value to the community.\n\n"
        . "🔧 **Features**\n\n"
        . "📈 **Token Info**: Get the latest KittyToken price, market data, and trading pairs.\n"
        . "🎁 **Airdrop**: Participate in our airdrop to receive free KittyToken rewards!\n"
        . "💼 **Pre-sale Info**: Learn about KittyToken's pre-sale details and how to participate.\n"
        . "🔗 **Liquidity Pool**: Check the latest status of the liquidity pool and how to join.\n"
        . "🗣️ **Community Support**: Get the latest updates, announcements, and community news about KittyToken.";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => 'openapp', 'url' => 'https://t.me/Kitty_Token_bot/airdrop']
            ],
        ]
    ];

    $reply_markup = json_encode($keyboard);
    sendInlineKeyboard($chat_id, $welcome_message, $reply_markup);
}

function invitationmessage($chat_id, $name)
{
    $welcome_message = "Your friend $name joined KittyToken";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => 'openapp', 'url' => 'https://t.me/Kitty_Token_bot/airdrop']
            ],
        ]
    ];

    $reply_markup = json_encode($keyboard);
    sendInlineKeyboard($chat_id, $welcome_message, $reply_markup);
}

function sendMessage($chat_id, $message)
{
    $token = '7290878766:AAEkROwwLM5ic4iN3PCcnvEUbHeckWq5S1Q';
    $api_url = "https://api.telegram.org/bot$token";
    $url = "$api_url/sendMessage?chat_id=$chat_id&text=" . urlencode($message);
    file_get_contents($url);
}

function sendInlineKeyboard($chat_id, $message, $reply_markup)
{
    $token = '7290878766:AAEkROwwLM5ic4iN3PCcnvEUbHeckWq5S1Q';
    $api_url = "https://api.telegram.org/bot$token";
    $url = "$api_url/sendMessage?chat_id=$chat_id&text=" . urlencode($message) . "&reply_markup=" . urlencode($reply_markup);
    file_get_contents($url);
}

function generateRandomCode($length = 7)
{
    // 定义可用的字符集（全部大写字母）
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    // 随机打乱字符集并截取指定长度
    $randomCode = substr(str_shuffle(str_repeat($characters, ceil($length / strlen($characters)))), 0, $length);

    return $randomCode;
}

function generateUniqueCode($existingCodes = [])
{
    do {
        $newCode = generateRandomCode();
    } while (in_array($newCode, $existingCodes)); // 检查是否在现有码中

    return $newCode;
}

// $a = 100;
// $a = 'hello';
// $a = ['hello', 'world', 'zhu'];
// dump($a);