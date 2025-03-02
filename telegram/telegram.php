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
    $url = "https://api.telegram.org/bot$apiToken/sendMessage";

    $message = MarkdownV2($photoId);

    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'MarkdownV2',  // 使用 MarkdownV2 解析模式
    ];


    $data['photo'] = $photoId; // 图片的 file_id


    if ($videoId) {
        $data['video'] = $videoId; // 图片的 file_id
    }
    if ($message_id) {
        $data['reply_to_message_id'] = $message_id; // 引用的消息 ID
    }
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
