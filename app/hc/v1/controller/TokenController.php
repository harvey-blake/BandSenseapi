<?php

namespace app\hc\v1\controller;

require dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'telegram' . DIRECTORY_SEPARATOR . 'telegram.php';


use Db\Db;
use function common\dump;
use function common\retur;

use function telegram\sendMessage;
use function telegram\botsendMessage;
use function telegram\MarkdownV2;
use function telegram\mute_member;
use function telegram\unmute_member;
use function telegram\send_unmute_button;
use function telegram\edit_message;




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
        $toaddress = MarkdownV2(substr($data['to'], -6));
        $fromaddress = MarkdownV2($data['from']);
        $name = MarkdownV2($data['name']);
        $value = MarkdownV2($data['value']);
        $hash = MarkdownV2($data['hash']);
        // 只转义会破坏 MarkdownV2 格式的特殊字符
        $message = "*【代币监听提醒】* \n\n"
            . "📥 您的钱包尾号 *$toaddress* 收到代币转账！\n"
            . "📌 代币名称 $name   \n"
            . "💰 代币数量 $value \n"
            . "🔗 交易哈希 [查看交易](https://polygonscan.com/tx/$hash) \n"
            . " ```来源地址
                $fromaddress
                ``` ";
        sendMessage($data['userid'], $message);
    }




    //接收消息
    public function bot($apiToken)
    {
        // 解析 Telegram 发送的数据
        $update = json_decode(file_get_contents("php://input"), true);
        // 检查是否有消息
        //
        if (isset($update['message']['new_chat_members'])) {
            $chat_id = $update['message']['chat']['id'];
            $new_members = $update['message']['new_chat_members'];

            foreach ($new_members as $member) {
                $user_id = $member['id'];
                $first_name = $member['first_name'];
                // 禁言新成员

                mute_member($chat_id, $user_id, $apiToken);
                //发送按钮
                send_unmute_button($chat_id, $user_id, $first_name, $apiToken);
            }
            exit;
        }
        // 处理按钮点击事件
        if (isset($update['callback_query'])) {
            $callback_data = $update['callback_query']['data'];
            $chat_id = $update['callback_query']['message']['chat']['id'];
            $from = $update['callback_query']['from']['id']; // 点击者的 user_id
            $message_id = $update['callback_query']['message']['message_id'];
            $user_id = explode('_', $callback_data)[1];

            if ($from != $user_id) {
                exit;
            }
            // 解除禁言
            edit_message($chat_id, $message_id, "欢迎加入 Token Transfer 社区！这是一个帮助您管理 Token 转账的工具!", $apiToken);
            unmute_member($chat_id, $user_id, $apiToken);

            // 更新消息内容，告知用户已解除禁言

            exit;
        }

        if (isset($update["message"]["left_chat_member"])) {
            exit;
        }


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
                $userMessage = isset($update["message"]["video"]["caption"]) ? $update["message"]["video"]["caption"] : $userMessage; // 获取视频的描述（如果有）
            }
            // 检查是否有图片
            if (isset($update["message"]["photo"])) {
                // Telegram 会提供不同尺寸的图片，这里取最后一个尺寸作为原始图片
                $photoId = $update["message"]["photo"][count($update["message"]["photo"]) - 1]["file_id"];
                $userMessage = isset($update["message"]["caption"]) ? $update["message"]["caption"] : $userMessage; // 获取图片的描述（如果有）
            }
            $adminId = '1882040053';  // 管理员 ID
            // 检查是否为引用消息
            if (isset($update["message"]["reply_to_message"])) {
                // 使用正则表达式提取用户 ID（假设 ID 在括号内）

                $replyToMessage = $update["message"]["reply_to_message"];
                $replyText =  $replyToMessage["text"];
                // 检查引用的消息是否包含图片
                if (isset($replyToMessage["photo"])) {
                    // Telegram 会提供不同尺寸的图片，取最后一个作为原始图片
                    $replyText = isset($replyToMessage["caption"]) ? $replyToMessage["caption"] : $replyText; // 获取图片描述，如果没有则为空

                }

                // 检查引用的消息是否包含视频
                if (isset($replyToMessage["video"])) {
                    $replyText = isset($replyToMessage["caption"]) ? $replyToMessage["caption"] : $replyText; // 获取视频描述
                }


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
                    //回复群消息
                    botsendMessage($apiToken, $originalChatId, $userMessage, $photoId, $videoId, $originalMessageId);
                } else if ($userId == $adminId) {
                    //回复用户信息
                    botsendMessage($apiToken, $originalUserId, $userMessage, $photoId, $videoId);
                } else if ($chatId < 0) {
                    $msg = "用户($userId)通过群<$chatId>[$messageId]说: $userMessage";
                    botsendMessage($apiToken, $adminId, $msg, $photoId, $videoId);
                } else {
                    $msg = "用户 ($userId) 说: $userMessage";
                    botsendMessage($apiToken, $adminId, $msg, $photoId, $videoId);
                }
            } else {
                // 普通用户的消息，转发给管理员
                if ($chatId < 0) {
                    $msg = "用户($userId)通过群<$chatId>[$messageId]说: $userMessage";
                    botsendMessage($apiToken, $adminId,  $msg, $photoId, $videoId);
                } else {
                    $msg = "用户 ($userId) 说: $userMessage";
                    botsendMessage($apiToken, $adminId, $msg, $photoId, $videoId);
                }
            }
        }
    }
}
