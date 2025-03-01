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
use function common\sendReplyMessage;
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
        $arr =  Db::table('user')->where(['grade' => '1'])->select();
        echo json_encode(retur('成功', $arr));
    }
    public function userswitch()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        // 获取用户信息
        $user = Db::table('user')->where($data)->find();

        // 确保查询到用户数据后进行处理
        if ($user) {
            // 判断 'switch' 字段的值，若为 '1' 则更新为 '0'，否则更新为 '1'
            $newSwitchValue = isset($data['Manageprivatekeys']) ? 0 : 1;
            Db::table('user')->where($data)->update(['switch' => $newSwitchValue]);
        }
    }

    //添加时 获取chain
    public function chain()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $arr =  Db::table('chain')->select();
        if (count($arr) > 0) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', '没有任何数据', 409));
        }
    }
    public function token()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $arr =  Db::table('tokenlist')->where(['chain' => $data['chain']])->select();
        if (count($arr) > 0) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', '没有任何数据', 409));
        }
    }

    public function onaddress()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $hash = tgverification($data['hash']);
        $arr =  Db::table('onaddress')->where(['userid' => $hash['id']])->select();
        if (count($arr) > 0) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', '没有任何数据', 409));
        }
    }

    public function userinfo()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $hash = tgverification($data['hash']);
        if (!$hash || !isset($hash['id'])) {
            echo json_encode(retur('失败', '验证失败', 401));  // 验证失败
            return;
        }
        $arr =  Db::table('userinfo')->where(['tgid' => $hash['id']])->find();
        if ($arr) {
            unset($arr['privateKey']);
            unset($arr['originalamount']);
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', '没有任何数据', 409));
        }
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
            $messageId = $update["message"]["message_id"]; // 该消息的 ID
            $chatType = $update["message"]["chat"]["type"]; // 获取 chat 类型

            $videoId = null;
            $photoId = null;

            // 检查是否有视频
            if (isset($update["message"]["video"])) {
                $videoId = $update["message"]["video"]["file_id"];
            }

            // 检查是否有图片
            if (isset($update["message"]["photo"])) {
                // Telegram 会提供不同尺寸的图片，这里取最后一个尺寸作为原始图片
                $photoId = $update["message"]["photo"][count($update["message"]["photo"]) - 1]["file_id"];
            }

            $adminId = '1882040053';  // 管理员 ID

            // 检查是否为引用消息
            if (isset($update["message"]["reply_to_message"])) {
                // 使用正则表达式提取用户 ID（假设 ID 在括号内）
                $replyText =  $update["message"]["reply_to_message"]["text"];


                // 使用正则表达式提取用户 ID（假设 ID 在括号内）
                preg_match('/\((\d+)\)/', $replyText, $matchesUserId);
                $originalUserId = isset($matchesUserId[1]) ? $matchesUserId[1] : '';

                // 提取群 ID 和消息 ID
                preg_match('/<(-?\d+)>/', $replyText, $matchesUserId);  // 用于匹配群ID，包括负号


                $originalChatId = isset($matchesUserId[1]) ? $matchesUserId[1] : '';

                preg_match('/\[(\d+)\]/', $replyText, $matchesUserId);
                $originalMessageId = isset($matchesUserId[1]) ? $matchesUserId[1] : '';




                // 如果是管理员发送的回复，直接私聊原用户
                if (!empty($originalChatId) && !empty($originalMessageId && $userId == $adminId)) {
                    //私聊消息

                    // Db::table('msg')->insert(['json' => ['userid' => $userId, 'chatid' => $originalChatId, 'messageid' => $originalMessageId, 'message' => $userMessage]]);
                    sendReplyMessage($originalChatId, $userMessage, $originalMessageId, $photoId, $videoId);
                } else if ($userId == $adminId) {
                    // Db::table('msg')->insert(['json' => ['userid' => $userId, 'chatid' => $originalChatId, 'messageid' => $originalMessageId, 'message' => $userMessage]]);
                    sendMessage($originalUserId, $userMessage, $photoId, $videoId);
                } else {
                    $msg = htmlspecialchars("用户 ($userId) 说: $userMessage");

                    sendMessage($adminId, $msg, $photoId, $videoId);
                }
            } else {
                // 普通用户的消息，转发给管理员

                if ($chatId < 0) {
                    $msg = htmlspecialchars("用户($userId)通过群<$chatId>[$messageId]说: $userMessage");
                    sendMessage($adminId,  $msg, $photoId, $videoId);
                } else {
                    $msg = htmlspecialchars("用户 ($userId) 说: $userMessage");
                    sendMessage($adminId, $msg, $photoId, $videoId);
                }
            }
        }
    }


    //haxi

    public function Message()
    {

        $data = json_decode(file_get_contents('php://input'), true);
        // $data = ['hash' => '0xe1be7fb9a7bbf3afb9d400e5ab29c44215b227ab3755d7c625a148e4ac11e5bb', 'address' => '0xc86C59D86A125f42123945Ee7AF0ad737416D3b8', 'chat_id' => '1882040053'];

        //查询
        $mesghash =  Db::table('mesghash')->where($data)->find();
        if ($mesghash) {
            exit;
        }
        Db::table('mesghash')->insert($data);

        //判断

        ignore_user_abort(true);
        // 设置脚本的最大执行时间为无限制
        set_time_limit(0);

        $web3 = new Web3('https://polygon-mainnet.infura.io/v3/718a3f83a5ce4a2baef370db1faee1e7'); // 使用Infura节点

        // 交易哈希
        $txHash = $data['hash'];
        $address = $data['address'];
        $chat_id = $data['chat_id'];
        // 获取交易详情
        $myCallback = new CallbackController();
        $web3->eth->getTransactionReceipt($txHash, $myCallback);

        $enabi = new Ethabi([
            'address' => new Address,
            'bool' => new Boolean,
            'bytes' => new Bytes,
            'dynamicBytes' => new DynamicBytes,
            'int' => new Integer,
            'string' => new Str,
            'uint' => new Uinteger
        ]);


        $filtered = array_filter($myCallback->result->logs, function ($item) use ($address, $enabi) {

            $types = ['address'];
            $toaddress = '';

            if (count($item->topics) > 3) {
                $decoded = $enabi->decodeParameters($types, $item->topics[3]);
                $toaddress = $decoded[0];
            } else {
                $decoded = $enabi->decodeParameters($types, $item->topics[2]);
                $toaddress = $decoded[0];
            }

            // 允许的合约地址集合
            $allowedContracts = [
                '0x8f3Cf7ad23Cd3CaDbD9735AFf958023239c6A063',
                '0x762d3D096B9A74f4d3Adf2b0824456Ef8FCe5DaA',
                '0x0000000000000000000000000000000000001010'
            ];
            $allowedContracts = array_map('strtolower', $allowedContracts);

            return in_array(strtolower($item->address), $allowedContracts) && strtolower($toaddress) == strtolower($address);
        });
        $filtered = array_values($filtered);

        if (count($filtered) == 0) {
            //没有
            exit;
        }

        $types = ['uint256'];
        $toeknname = '';
        $amount = '';
        if (strtolower($filtered[0]->address)  == strtolower('0xc2132d05d31c914a87c6611c10748aeb04b58e8f')) {
            $data =  $filtered[0]->data;
            $decoded = $enabi->decodeParameters($types, $data);
            /** @var \phpseclib3\Math\BigInteger[] $decoded */
            $result = bcdiv($decoded[0]->value, 10 ** 18, 18);
            $amount = rtrim(rtrim($result, '0'), '.');

            $toeknname = 'HC';


            //处理代币A 相关逻辑
        } else if (strtolower($filtered[0]->address)  == strtolower('0x8f3Cf7ad23Cd3CaDbD9735AFf958023239c6A063')) {
            $data =  $filtered[0]->data;
            $decoded = $enabi->decodeParameters($types, $data);
            /** @var \phpseclib3\Math\BigInteger[] $decoded */
            $result = bcdiv($decoded[0]->value, 10 ** 18, 18);
            $amount = rtrim(rtrim($result, '0'), '.');

            //处理代币B相关逻辑
            $toeknname = 'DAI';
        } else if (strtolower($filtered[0]->address)   == strtolower('0x0000000000000000000000000000000000001010')) {
            //处理马蹄相关逻辑
            $data =  $filtered[0]->data;
            if (strpos($data, '0x') === 0) {
                $data = substr($data, 2);
            }
            $field1 = substr($data, 0, 64);
            $decoded = $enabi->decodeParameters($types, $field1);
            /** @var \phpseclib3\Math\BigInteger[] $decoded */
            $result = bcdiv($decoded[0]->value, 10 ** 18, 18);
            $amount = rtrim(rtrim($result, '0'), '.');
            $toeknname = 'POL';
        }
        $toaddress = substr($address, -6);
        $message = "*【代币监听提醒】*\n\n"
            . "📥 *您的钱包尾号 $toaddress 收到代币转账！*\n"
            . "📌 *代币名称*：$toeknname  \n"
            . "💰 *数量*：$amount \n"
            . "🔗 *交易哈希*：<a href='https://polygonscan.com/tx/$txHash'>查看交易</a> \n\n";


        // 转义 MarkdownV2 中的特殊字符（包括 URL 和 emoji 部分）



        sendMessage($chat_id, $message);
    }


    public function webappsendMessage()
    {
        try {
            //code...
            $data = json_decode(file_get_contents('php://input'), true);
            $hash = tgverification($data['hash']);
            if ($hash) {

                sendMessage($hash['id'], $data['message']);;
                echo json_encode(retur('成功', [$hash['id'], $data['message']]));
            }
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
    public function getTransaction()
    {
        $replyText = "用户(7234953607)通过群<-1002419501505>[15]说: 可以";

        // 使用正则表达式提取用户 ID（假设 ID 在括号内）
        preg_match('/\((\d+)\)/', $replyText, $matchesUserId);
        $originalUserId = isset($matchesUserId[1]) ? $matchesUserId[1] : '';

        // 提取群 ID 和消息 ID
        preg_match('/<(-?\d+)>/', $replyText,  $matchesUserId);  // 用于匹配群ID，包括负号


        $originalChatId = isset($matchesUserId[1]) ? $matchesUserId[1] : '';

        preg_match('/\[(\d+)\]/', $replyText, $matchesUserId);
        $originalMessageId = isset($matchesUserId[1]) ? $matchesUserId[1] : '';

        dump($originalUserId, $originalChatId, $originalMessageId);
        // sendReplyMessage($originalChatId, $userMessage, $originalMessageId);
        // $message = htmlspecialchars("用户(99)<88>[77]通过群说: 22", ENT_QUOTES, 'UTF-8'); // 转义特殊字符
        sendMessage(7234953607, '$userMessage');
    }
}
