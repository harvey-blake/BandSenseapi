<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\api\v1\controller;


use Db\Db;
use function common\dump;
use function common\retur;
use function common\sendMessage;
use function common\Message;
use function common\tgverification;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use common\Controller;

class  UpdateController extends Controller
{
    public function Strategystate()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $user = self::validateJWT();
            $state =  Db::table('Strategy')->field('state')->where(['id' => $data['id'], 'userid' => $user['id']])->find();
            $state = $state['state'] ^ "1";
            $arr =  Db::table('Strategy')->where(['id' => $data['id'], 'userid' => $user['id']])->update(['state' => $state]);
            if ($arr > 0) {
                echo json_encode(retur('成功', $arr));
            } else {
                echo json_encode(retur('失败', '没更改任何数据', 409));
            }
        } catch (\Throwable $th) {

            echo json_encode(retur('失败', '非法访问', 500));
        }
    }

    public function subscription()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $template_path = __DIR__ . '/../mail/subscription.html'; // 替换为模板文件的实际路径

        $template_content = file_get_contents($template_path);

        self::mail('3005779@qq.com', '恭喜您订阅成功', $template_content);
        // echo json_encode(retur('成功', self::mail($data['mail'], '恭喜您,订阅成功', $template_content)));
    }



    public function mail($to, $title, $content)
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
}