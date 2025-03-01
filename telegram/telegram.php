<?php
// 电报函数库

namespace telegram;


function sendMessage($chatId, $message)
{

    $apiToken = '7949382682:AAGhPeyqz4ru183scmko8bIjdxp37G3Bs0k';
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
}
