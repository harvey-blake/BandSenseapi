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
        $data = json_decode(file_get_contents('php://input'), true);



        // 原始消息内容

        $toaddress = substr($data['to'], -6);
        $fromaddress = $data['from'];
        $name = $data['name'];
        $value = $data['value'];
        $hash = $data['hash'];

        // 只转义会破坏 MarkdownV2 格式的特殊字符
        $message = "*【代币监听提醒】* \n\n"
            . "📥 *您的钱包尾号 $toaddress 收到代币转账！*\n"
            . "📓 *来源地址* $fromaddress "
            . "📌 *代币名称* $name   \n"
            . "💰 *代币数量* $value \n"
            . "🔗 *交易哈希*：[查看交易](https://polygonscan.com/tx/$hash) \n";




        sendMessage($data['userid'], $message);
    }
}
