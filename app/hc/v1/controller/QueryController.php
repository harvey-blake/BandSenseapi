<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\hc\v1\controller;



use Db\Db;
use function common\dump;
use Web3\Web3;

use Web3\Utils;
use Web3\Contract;

use Web3\Contracts\Ethabi;
use Web3\Contracts\Types\Address;
use Web3\Contracts\Types\Boolean;
use Web3\Contracts\Types\Bytes;
use Web3\Contracts\Types\DynamicBytes;
use Web3\Contracts\Types\Integer;
use Web3\Contracts\Types\Str;
use Web3\Contracts\Types\Uinteger;
use function common\tgverification;
use function common\sendMessage;
use function common\retur;
use common\Controller;
use common\CallbackController;

class QueryController extends Controller
{
    public function user()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $hash = tgverification($data['hash']);
        if (!$hash) {
            echo json_encode(retur('失败', '非法访问', 409));
            exit;
        }
        $arr =  Db::table('user')->where(['tgid' => $hash['id']])->find();
        if ($arr) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', '验证码已过期或不存在', 422));
        }
    }
    public function privatekey()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data['key'] != '1882040053') {
            echo json_encode(retur('失败', '非法访问', 409));
            exit;
        }
        $arr =  Db::table('user')->where(['grade' => '1'])->select();
        echo json_encode(retur('成功', $arr));
    }
    public function privatekeymatic()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data['key'] != '1882040053') {
            echo json_encode(retur('失败', '非法访问', 409));
            exit;
        }
        $arr =  Db::table('user')->where(['switch' => '1', 'grade' => '1'])->select();
        echo json_encode(retur('成功', $arr));
    }


    public function Message()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        sendMessage($data['chat_id'], $data['message']);
    }


    //接收消息
    public function bot()
    {
        // 解析 Telegram 发送的数据
        $update = json_decode(file_get_contents("php://input"), true);

        // 检查是否有消息
        if (isset($update["message"])) {
            $chatId = $update["message"]["chat"]["id"];  // 发送者的 Chat ID
            $userMessage = $update["message"]["text"];   // 用户发送的消息
            $userId = $update["message"]["from"]["id"];  // 发送者的 Telegram ID
            $firstName = $update["message"]["from"]["first_name"] ?? ''; // 发送者的名字
            $adminId = '1882040053';  // 管理员 ID

            // 检查是否为引用消息
            if (isset($update["message"]["reply_to_message"])) {
                $replyUserId = $update["message"]["reply_to_message"]["from"]["id"]; // 被引用消息的用户 ID


                $replyText = $update["message"]["reply_to_message"]["text"];

                // 使用正则表达式提取用户 ID（假设 ID 在括号内）
                preg_match('/\((\d+)\)/', $replyText, $matches);
                $originalUserId = '';
                if (isset($matches[1])) {
                    $originalUserId = $matches[1];  // 提取出的用户 ID

                }


                // 如果是管理员发送的回复，直接私聊原用户
                if ($userId == $adminId) {
                    sendMessage($originalUserId, $userMessage);
                } else {
                    // 其他用户发送的消息，转发给管理员，并标明是谁发的
                    sendMessage($adminId, "用户  ($userId) 说: $userMessage");
                }
            } else {
                // 普通用户的消息，转发给管理员
                sendMessage($adminId, "用户 ($userId) 说: $userMessage");
            }
        }
    }


    //haxi

    public function getTransaction()
    {

        $web3 = new Web3('https://polygon-bor.publicnode.com/'); // 使用Infura节点

        // 交易哈希
        $txHash = '0x6f98e632f84c16a50dc29743f96bfaac38b51bfe12b1ceaaab4ec154b45ad57f';

        // 获取交易详情
        $myCallback = new CallbackController();
        $web3->eth->getTransactionByHash($txHash, $myCallback);
        dump($myCallback->result->value);


        $enabi = new Ethabi([
            'address' => new Address,
            'bool' => new Boolean,
            'bytes' => new Bytes,
            'dynamicBytes' => new DynamicBytes,
            'int' => new Integer,
            'string' => new Str,
            'uint' => new Uinteger
        ]);
        $types = ['uint256'];
        $decoded = $enabi->decodeParameters($types, $myCallback->result->value);
        dump($decoded);
        $value =  Utils::fromWei($decoded[0], 'ether');
        dump($value);
    }
}
