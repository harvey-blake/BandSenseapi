<?php

// 控制器类的基类 相当于所有控制器的祖先

namespace common;

use Db\Db;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Ramsey\Uuid\Uuid;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Validator;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Controller
{

    protected  $myCallback;
    // 构造器
    public function __construct()
    {
        $this->myCallback = new CallbackController();
    }


    // 获取JWT  需要测试的
    public function JWT()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            // 验证账号 是否存在

            $arr =  Db::table('cex_user')->field('*')->where(['username' => $data['username'], 'password' => $data['password']])->find();

            if ($arr) {

                $username = $arr['username'];
                $password = $arr['password'];
                $expirationTimeInMinutes = 10080;
                $key = InMemory::plainText($password);
                $config = Configuration::forSymmetricSigner(
                    new Sha256(),
                    $key
                );
                $systemTimezone = date_default_timezone_get(); // 获取系统默认时区
                $timezone = new \DateTimeZone($systemTimezone);
                $currentTime = new \DateTimeImmutable('now', $timezone);
                $expirationTime = $currentTime->modify("+$expirationTimeInMinutes minutes");
                $builder = $config->builder()
                    ->issuedAt($currentTime) // 签发时间
                    ->canOnlyBeUsedAfter($currentTime) // 生效时间
                    ->expiresAt($expirationTime); // 过期时间
                $builder->withClaim('username', $username);
                $builder->withClaim('password', $password);
                $token = $builder->getToken($config->signer(), $config->signingKey());
                $token = encryptData($token->toString());
                $uniqid = Uuid::uuid4();
                $uniqid = $uniqid->toString();
                //这里判断下 存哪个仓库
                $table = 'LoginKey';
                if (isset($data['type']) && $data['type'] == 1) {
                    $table = 'HangupLoginKey';
                }

                $arr =  Db::table($table)->field('*')->where(['username' => $username])->find();
                if ($arr) {
                    // 修改
                    $arr =  Db::table($table)->where(['username' => $username])->update(['username' => $username, 'keyid' => $uniqid, 'token' => $token]);
                } else {
                    // 添加
                    $arr =  Db::table($table)->insert(['username' => $username, 'keyid' => $uniqid, 'token' => $token]);
                }

                if ($arr > 0) {
                    echo json_encode(retur('成功', $uniqid));
                } else {
                    echo json_encode(retur('失败', '网络拥堵请稍后再试', 9000));
                }
            } else {
                echo json_encode(retur('失败', '账号或密码错误', 9000));
            }
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage();
            $errorLine = $th->getLine();
            echo json_encode(retur('失败', "错误信息：{$errorMessage}，发生在第 {$errorLine} 行。", 9000));
        }
    }

    // 获取用户信息
    public function login()
    {
        try {
            $data =  self::validateJWT();
            echo json_encode(retur('成功', $data));
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', $th, 9000));
        }
    }
    // 验证JWT
    function validateJWT()
    {

        // 解密
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        // $headers = getallheaders();
        $data = $_SERVER['HTTP_AUTHORIZATION'];

        // dump($_SERVER['HTTP_AUTHORIZATION']);
        // $data = $_GET['token'];
        if (!$data) {
            // 账号未登录
            echo json_encode(retur('错误', '账号未登陆', 403));
            exit;
        }
        $keyid = str_replace('Bearer ', '', $data);
        $data =  Db::table('LoginKey')->field('*')->where(['keyid' => $keyid])->find();
        if (!$data) {
            $data =  Db::table('HangupLoginKey')->field('*')->where(['keyid' => $keyid])->find();
        }
        // 获取数据库
        if ($data) {
            $data =  $data['token'];
            $data = decryptData($data);
        } else {
            // 这里返回账号在其他地方登陆
            echo json_encode(retur('出错了', '账号在其他地方登陆', 498));
            exit;
        }

        if ($data) {
            $parser = new Parser(new JoseEncoder());
            $token = $parser->parse($data);
            // 创建验证器
            $validator = new Validator();
            // 获取系统默认时区
            $systemTimezone = date_default_timezone_get();
            $timezone = new \DateTimeZone($systemTimezone);
            $time = new SystemClock($timezone);
            // 使用ValidAt约束验证令牌的时间范围

            if (!$validator->validate($token, new StrictValidAt($time)) || !$validator->validate($token, new SignedWith(new Sha256(), InMemory::plainText($token->claims()->get('password'))))) {
                // 令牌在时间范围之外
                return false;
            } else {
                // 考虑验证签名https://lcobucci-jwt.readthedocs.io/en/latest/validating-tokens/
                // 成功 返回用户账号密码
                $arr =   Db::table('cex_user')->field('*')->where(['username' => $token->claims()->get('username')])->find();
                return $arr;
            }
        } else {
            //账号登陆失效
            echo json_encode(retur('出错了', '授权已过期', 401));
            exit;
        }
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
