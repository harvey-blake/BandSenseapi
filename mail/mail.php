<?php
// 邮件 发送与验证

namespace bandsenmail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use function common\retur;
use Db\Db;


function mail($to, $title, $text)
{
    $ip = $_SERVER['REMOTE_ADDR'];




    $time = date('Y-m-d H:i:s', strtotime('-24 hours'));
    //首先 同一个IP不能超过三次
    //其次 同一个邮箱不能超过三次
    $isip =  Db::table('mailcode')->field('*')->where(['ip' => $ip, 'time >=' => $time])->count();
    if ($isip >= 5) {
        echo json_encode(retur('失败', '账户被锁定,请24小时候再试', 405));
        exit;
    }
    $ismail =  Db::table('mailcode')->field('*')->where(['mail' => strtolower($to), 'time >=' => $time])->count();
    if ($ismail >= 5) {
        echo json_encode(retur('失败', '账户被锁定,请24小时候再试', 405));
        exit;
    }

    //判断是否可以发送邮件
    $verificationCode = rand(100000, 999999);
    $template_path = __DIR__ . '/../mail/subscription.html'; // 替换为模板文件的实际路径
    $template_content = file_get_contents($template_path);

    // 替换模板中的验证码（假设验证码使用 {code} 占位符）
    $htmlContent = str_replace('{code}', $verificationCode, $template_content);
    $htmlContent = str_replace('{text}', $text, $htmlContent);
    $htmlContent = str_replace('{title}', $title, $htmlContent);

    send($to, $title, $htmlContent);

    Db::table('mailcode')->insert(['mail' => strtolower($to), 'code' => $verificationCode, 'ip' => $ip]);
}

function send($to, $title, $content)
{
    $mail = new PHPMailer();
    try {
        $mail = new PHPMailer();
        // 配置邮件服务器设置
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;

        $state =  Db::table('mailsmpt')->field('*')->where(['id' => 1])->find();
        $mail->isSMTP(); // 使用 SMTP 发送
        $mail->Host = $state['Host']; // 设置 SMTP 服务器地址
        $mail->SMTPAuth = true; // 启用 SMTP 认证
        $mail->Username = $state['Username']; // SMTP 用户名
        $mail->Password = $state['Password']; // SMTP 密码
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465; // 设置 SMTP 端口号
        // 设置字符编码为 UTF-8
        $mail->CharSet = 'UTF-8';
        // 设置邮件内容
        $mail->setFrom('dexcpro@gmail.com', 'DEXC'); // 发件人邮箱和姓名
        $mail->addAddress($to); // 收件人邮箱和姓名
        $mail->isHTML(true);
        $mail->Subject = $title; // 邮件主题
        $mail->Body =  $content; // 邮件正文
        return $mail->send();
    } catch (Exception $e) {
        return false;
        // return  $mail->ErrorInfo;
    }
}
