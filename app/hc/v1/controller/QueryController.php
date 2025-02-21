<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\hc\v1\controller;



use Db\Db;
use function common\dump;
use function common\tgverification;
use function common\sendMessage;
use function common\retur;
use common\Controller;

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

                // 如果是管理员发送的回复，直接私聊原用户
                if ($userId == $adminId) {
                    sendMessage($replyUserId, "管理员回复你：$userMessage");
                } else {
                    // 其他用户发送的消息，转发给管理员，并标明是谁发的
                    sendMessage($adminId, "用户 $firstName ($userId) 说: $userMessage");
                }
            } else {
                // 普通用户的消息，转发给管理员
                sendMessage($adminId, "用户 $firstName ($userId) 说: $userMessage");
            }
        }
    }
}
