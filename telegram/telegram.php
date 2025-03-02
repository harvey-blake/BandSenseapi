<?php
// 电报函数库

namespace telegram;

function MarkdownV2($text)
{
    // 转义需要的字符
    $specialChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
    foreach ($specialChars as $char) {
        $text = str_replace($char, '\\' . $char, $text);
    }
    return $text;
}




function sendMessage($chatId, $message)
{

    $apiToken = '7949382682:AAGhPeyqz4ru183scmko8bIjdxp37G3Bs0k';
    $url = "https://api.telegram.org/bot$apiToken/sendMessage";

    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'MarkdownV2',  // 使用 MarkdownV2 解析模式
    ];

    $postData = http_build_query($data);

    // 使用 file_get_contents 发送请求
    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $postData,
        ]
    ];
    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}

function botsendMessage($apiToken, $chatId, $message, $photoId = null, $videoId = null, $message_id = null)
{

    $apiToken = $apiToken;


    $message = MarkdownV2($message);
    $method = 'sendMessage';
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'MarkdownV2',  // 使用 MarkdownV2 解析模式
    ];

    if ($photoId) {
        $data['photo'] = $photoId; // 图片的 file_id
        $data['caption'] = $message; // 视频的文本消息
        $method = 'sendPhoto';
    };

    if ($videoId) {
        $data['video'] = $videoId; // 图片的 file_id
        $method = 'sendVideo';
        $data['caption'] = $message; // 视频的文本消息
    }
    if ($message_id) {
        $data['reply_to_message_id'] = $message_id; // 引用的消息 ID
    }


    $url = "https://api.telegram.org/bot$apiToken/$method";
    $postData = http_build_query($data);

    // 使用 file_get_contents 发送请求
    $options = [
        'http' => [
            'method'  => 'POST',
            'content' => $postData,
        ]
    ];
    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}


function mute_member($chat_id, $user_id, $token)
{
    // 禁言新成员
    $api_url = "https://api.telegram.org/bot" . $token . "/restrictChatMember";
    $post_fields = [
        'chat_id' => $chat_id,
        'user_id' => $user_id,
        'permissions' => json_encode([
            'can_send_messages' => false,
            'can_send_media_messages' => false,
            'can_send_other_messages' => false,
            'can_add_web_page_previews' => false,
        ])
    ];
    send_request($api_url, $post_fields);
}

function unmute_member($chat_id, $user_id, $token)
{
    // 解除禁言
    $api_url = "https://api.telegram.org/bot" . $token . "/restrictChatMember";
    $post_fields = [
        'chat_id' => $chat_id,
        'user_id' => $user_id,
        'permissions' => json_encode([
            'can_send_messages' => true,
            'can_send_media_messages' => true,
            'can_send_other_messages' => true,
            'can_add_web_page_previews' => true,
        ])
    ];

    send_request($api_url, $post_fields);
}

function send_unmute_button($chat_id, $user_id, $first_name, $token)
{
    // 发送带按钮的消息
    $api_url = "https://api.telegram.org/bot" . $token . "/sendMessage";
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '点击解除禁言', 'callback_data' => "unmute_" . $user_id]
            ]
        ]
    ];
    $post_fields = [
        'chat_id' => $chat_id,
        'text' =>  "欢迎 " . $first_name . " 点击按钮以解除禁言。",
        'reply_markup' => json_encode($keyboard)
    ];
    send_request($api_url, $post_fields);
}


function edit_message($chat_id, $message_id, $text, $token)
{
    // 更新消息内容
    $api_url = "https://api.telegram.org/bot" . $token . "/editMessageText";

    $keyboard = [
        'inline_keyboard' => [
            [
                [
                    'text' => "账户管理",
                    'url' => "https://t.me/Token_transferbot/app" // 按钮打开的链接
                ]

            ]
        ]
    ];

    $post_fields = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'reply_markup' => json_encode($keyboard)
    ];
    send_request($api_url, $post_fields);
}

function send_request($url, $post_fields)
{
    // 使用 cURL 发送请求
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}
