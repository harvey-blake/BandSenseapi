<?php

namespace app\hc\v1\controller;

require dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'telegram' . DIRECTORY_SEPARATOR . 'telegram.php';


use Db\Db;
use function common\dump;
use function common\retur;
use function common\tgverification;
use function common\mnemonic;
use Web3\Web3;
use Web3\Contract;
use common\CallbackController;
use Web3\Providers\HttpAsyncProvider;
use function telegram\sendMessage;




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

        // 原始消息内容




        //         $message = "*bold \*text*
        //  _italic \*text_
        // __underline__
        // ~strikethrough~
        // ||spoiler||
        // *bold _italic bold ~italic bold strikethrough ||italic bold strikethrough spoiler||~ __underline italic bold___ bold*
        // [inline URL](http://www.example.com/)
        // [inline mention of a user](tg://user?id=123456789)
        // ![👍](tg://emoji?id=5368324170671202286)
        // `inline fixed-width code`
        // ```
        // pre-formatted fixed-width code block
        // ```
        // ```python
        // pre-formatted fixed-width code block written in the Python programming language
        // ```
        // >Block quotation started
        // >Block quotation continued
        // >Block quotation continued
        // >Block quotation continued
        // >The last line of the block quotation
        // **>The expandable block quotation started right after the previous block quotation
        // >It is separated from the previous block quotation by an empty bold entity
        // >Expandable block quotation continued
        // >Hidden by default part of the expandable block quotation started
        // >Expandable block quotation continued
        // >The last line of the expandable block quotation with the expandability mark||";

        // 只转义会破坏 MarkdownV2 格式的特殊字符
        $message = "*【代币监听提醒】* \n\n"
            . "📥 *您的钱包尾号  收到代币转账！*\n"
            . "📌 *代币名称*   \n"
            . "💰 *数量*： \n"
            . "🔗 *交易哈希*：[查看交易](https://polygonscan.com/tx/0x948c83afa2b69438fbbda643ced62a68b53ba3ceafb2501340d0b9b9520901e4) \n"
            .  " ```
             pre-formatted fixed-width code block
                ``` ";
        $url = "https://api.telegram.org/bot$apiToken/sendMessage";
        $postData = http_build_query([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'MarkdownV2',  // 使用 MarkdownV2 解析模式
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


        sendMessage($chatId, $message);
    }
}
