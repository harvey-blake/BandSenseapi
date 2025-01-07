<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\dapp\v1\controller;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

use Db\Db;

use function common\dump;
use function common\retur;
use common\CallbackController;

class QueryController
{
    public function index()
    {
        dump('开始');
    }
    public function user()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $arr =  Db::table('tg_user')->field('*')->where(['tgid' => $data])->find();
        // 还需要查询有多少下线
        //
        $count =  Db::table('tg_user')->where(['SuperiorID' => $data])->count();
        if ($arr) {
            $arr['count'] = $count;
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', $arr, 422));
        }
    }
    public function components()
    {
        // limit 查询数量
        // page 当前页
        $data = json_decode(file_get_contents('php://input'), true);
        $arr =  Db::table('tg_user')->where(['SuperiorID' => $data['id']])->order('id', 'asc')->limit($data['limit'])->page($data['page'])->select();
        $count =  Db::table('tg_user')->where(['SuperiorID' => $data['id']])->count();
        $remt = ['list' => $arr, 'count' => $count];
        echo json_encode(retur('成功', $remt));
    }
    public function chain()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $arr =  Db::table('tg_chain')->field('*')->where(['chain' => $data['chain']])->find();
        if ($arr) {
            echo json_encode(retur('成功',  $arr));
        } else {
            echo json_encode(retur('失败', $arr, 422));
        }
    }
    public function chainlist()
    {
        $arr =  Db::table('tg_chain')->field('*')->select();
        if ($arr) {
            echo json_encode(retur('成功',  $arr));
        } else {
            echo json_encode(retur('失败', $arr, 422));
        }
    }
    public function tokenlist()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $arr =  Db::table('tg_tokenlist')->field('*')->where(['chain' => $data['chain']])->select();
        if ($arr) {
            echo json_encode(retur('成功',  $arr));
        } else {
            echo json_encode(retur('失败', $arr, 422));
        }
    }


    public function botPermissions()
    {
        $data = $_SERVER['HTTP_AUTHORIZATION'];
        if ($data) {
            $arr =  Db::table('tg_mac')->field('*')->where(['userid' => $data])->find();
            if ($arr && $arr['endtime'] > time()) {
                echo json_encode(retur($data, true));
            } else {
                echo json_encode(retur('失败', false, 422));
            }
        } else {
            echo json_encode(retur('失败', false, 422));
        }
    }


    public function subscription()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $template_path = __DIR__ . '/../mail/subscription.html'; // 替换为模板文件的实际路径

        $template_content = file_get_contents($template_path);

        // self::mail('3005779@qq.com', '恭喜您订阅成功', $template_content);
        echo json_encode(retur('成功', self::mail($data['mail'], '恭喜您,订阅成功', $template_content)));
    }



    public function mail($to, $title, $content)
    {
        $mail = new PHPMailer();
        try {
            $mail = new PHPMailer();
            // 配置邮件服务器设置
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->isSMTP(); // 使用 SMTP 发送
            $mail->Host = 'email-smtp.ap-southeast-1.amazonaws.com'; // 设置 SMTP 服务器地址
            $mail->SMTPAuth = true; // 启用 SMTP 认证
            $mail->Username = 'AKIARZBOBYRZJ6XY5MON'; // SMTP 用户名
            $mail->Password = 'BAXAcqcm0BoK+OzOdxts+XyV5H1sHNtSfr1yq/6MLcH/'; // SMTP 密码
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            // $mail->SMTPSecure = 'tls'; // 加密类型
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
