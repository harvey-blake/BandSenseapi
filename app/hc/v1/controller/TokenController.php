<?php

namespace app\hc\v1\controller;

require dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'telegram' . DIRECTORY_SEPARATOR . 'telegram.php';


use Db\Db;
use function common\dump;
use function common\retur;

use function telegram\sendMessage;
use function telegram\MarkdownV2;




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
        $data = json_decode(file_get_contents('php://input'), true);

        $data = [
            'from' => '0x41ad0579f1555ee49dbb13a34c26525777777777',
            'to' => '0xc86c59d86a125f42123945ee7af0ad737416d3b8',
            'value' => '0.0001',
            'name' => 'DAI',
            'hash' => '0x47842f099049d9a840b1af7af022a20bf07fc71c864a1ca2ad2a2bf56d7857d3',
            'userid' => '1882040053'
        ];

        // 原始消息内容

        $toaddress = MarkdownV2(substr($data['to'], -6));


        $fromaddress = MarkdownV2($data['from']);
        $name = MarkdownV2($data['name']);
        $value = MarkdownV2($data['value']);
        $hash = MarkdownV2($data['hash']);

        // 只转义会破坏 MarkdownV2 格式的特殊字符
        $message = "*【代币监听提醒】* \n\n"
            . "📥 *您的钱包尾号 $toaddress 收到代币转账！*\n"
            . "📓 *来源地址* ||$fromaddress||  \n"
            . "📌 *代币名称* $name   \n"
            . "💰 *代币数量* $value \n"
            . "🔗 *交易哈希*：[查看交易](https://polygonscan.com/tx/$hash) \n";

        dump($message);


        sendMessage($data['userid'], $message);
    }
}
