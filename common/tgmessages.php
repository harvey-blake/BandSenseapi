<?php

namespace common;

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

function edit_message($chat_id, $message_id, $text, $token)
{
    // 更新消息内容
    $api_url = "https://api.telegram.org/bot" . $token . "/editMessageText";

    $keyboard = [
        'inline_keyboard' => [
            [
                [
                    'text' => "下载地址",
                    'url' => "https://github.com/dexcpro/mix_tool/releases" // 按钮打开的链接
                ]
            ],
            [
                [
                    'text' => "软件绑定",
                    'url' => "https://t.me/mixtool_bot/vip" // 按钮打开的链接
                ],
                [
                    'text' => "使用教程",
                    'url' => "https://t.me/mix_toolnews" // 按钮打开的链接
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
