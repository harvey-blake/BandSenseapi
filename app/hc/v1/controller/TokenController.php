<?php

namespace app\hc\v1\controller;

use Db\Db;
use function common\dump;
use function common\retur;
use function common\tgverification;
use function common\mnemonic;
use Web3\Web3;
use Web3\Contract;
use common\CallbackController;
use Web3\Providers\HttpAsyncProvider;




use common\Controller;
// 写入
class TokenController extends Controller
{
    public function getchain()
    {
        $arr =  Db::table('chain')->select();

        echo json_encode(retur('成功', $arr));
    }

    public function gettoken()
    {
        //除开0地址  根据链ID 获取
        $data = json_decode(file_get_contents('php://input'), true);
        $arr =  Db::table('tokenlist')->where(['chain' => $data['chain'], 'address !=' => '0x0000000000000000000000000000000000001010'])->select();
        echo json_encode(retur('成功', $arr));
    }
    public function getaddress()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $arr =  Db::table('onaddress')->where(['token' => $data['token']])->select();
        echo json_encode(retur('成功', $arr));
    }
    public function message()
    {

        // 配置写在函数内部
        $apiToken = '7949382682:AAGhPeyqz4ru183scmko8bIjdxp37G3Bs0k';  // 替换成你的 Bot Token
        $chatId = "1882040053";               // 替换成你的 Chat ID

        // 要发送的消息
        $message = "[inline URL](http://www.example.com/)";

        // 转义 MarkdownV2 特殊字符
        $specialChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        foreach ($specialChars as $char) {
            $message = str_replace($char, '\\' . $char, $message);
        }

        // 构建请求 URL 和数据
        $url = "https://api.telegram.org/bot$apiToken/sendMessage";
        $postData = http_build_query([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'MarkdownV2',
        ]);

        // 使用 file_get_contents 发送请求
        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
            ]
        ];
        $context  = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        // 输出结果
        dump($response);
    }
}
