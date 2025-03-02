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
    $url = "https://api.telegram.org/bot$apiToken/sendMessage";

    // 对消息进行 MarkdownV2 转义
    $message = MarkdownV2($message);

    // 基础数据
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'MarkdownV2',  // 使用 MarkdownV2 解析模式
    ];

    // 如果有图片 file_id
    if ($photoId) {
        $data['photo'] = $photoId;  // 使用图片的 file_id
        $data['caption'] = $message;  // 图片的文本消息（可选）
    }

    // 如果有视频 file_id
    if ($videoId) {
        $data['video'] = $videoId;  // 使用视频的 file_id
        $data['caption'] = $message;
    }

    // 如果有回复消息的 ID
    if ($message_id) {
        $data['reply_to_message_id'] = $message_id; // 引用的消息 ID
    }

    // 使用 cURL 发送请求
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);  // 使用 multipart/form-data 格式上传数据

    // 执行请求并获取响应
    $response = curl_exec($ch);
    curl_close($ch);

    // 可选择打印响应结果，查看是否返回了错误信息
    // echo $response;
}
