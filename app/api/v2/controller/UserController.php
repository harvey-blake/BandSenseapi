<?php

namespace app\api\v2\controller;

require dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'mail' . DIRECTORY_SEPARATOR . 'mail.php';

use Db\Db;
use function common\dump;
use function common\retur;
use function bandsenmail\mail;
use function common\mnemonic;
use function common\getIPPrefix;
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

use function common\encryptData;
use function common\decryptData;
use common\Controller;



// 写入
class UserController extends Controller
{

    public function register()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        //判断 邮箱  密码  是否存在
        if (!isset($data['email']) || !isset($data['password'])) {
            echo json_encode(retur('失败', '参数错误', 422));
            exit;
        }

        if (isset($data['Superior'])) {
            $arr =  Db::table('user')->where(['Superior' => $data['Superior']])->find();
            if (!$arr) {
                echo json_encode(retur('失败', '邀请码不存在', 494));
                exit;
            }
        }

        //判断邮箱是否存在
        $arr =  Db::table('user')->where(['email' => $data['email']])->find();
        if ($arr) {
            echo json_encode(retur('失败', '邮箱已存在,请更换', 495));
            exit;
        }

        //判断验证码是否存在
        if (!isset($data['code'])) {
            //发送验证码
            mail($data['email'], '波段智投-用户注册', '注册新账户');
            exit;
        }
        //判断验证码是否正确
        $time = date('Y-m-d H:i:s', strtotime('-5 minutes'));

        $arr =  Db::table('mailcode')->where(['mail' => $data['email'], 'time >=' => $time])->order('id',  'desc')->limit(1)->select();

        if ($arr[0]['code'] != $data['code']) {
            echo json_encode(retur('失败', '验证码错误', 493));
            exit;
        }

        //写入数据库

        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'; // 仅大写字母
        $charactersLength = strlen($characters);

        do {
            // 生成一个随机字符串
            $randomString = '';
            for ($i = 0; $i < 8; $i++) {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }

            // 查询数据库，检查生成的随机字符串是否已经存在
            $exists = Db::table('user')->field('*')->where(['Invitationcode' =>  $randomString])->count();
        } while ($exists > 0); // 如果存在，继续生成新的字符串
        $array = array_filter($data);
        unset($array['code']);
        $array['Invitationcode'] = $randomString; // 在数组中增加新值
        $mnemonic = mnemonic();
        $array['privateKey'] = $mnemonic['privateKey']; // 私钥
        $array['address'] = $mnemonic['address']; // 地址
        $ip = $_SERVER['REMOTE_ADDR'];
        $array['ip'] = $ip; // 地址
        $arr =  Db::table('user')->insert($array);
        if ($arr) {
            echo json_encode(retur('成功', '注册成功'));
        } else {
            echo json_encode(retur('失败', '注册失败', 422));
        }
    }

    public function Login()
    {

        $data = json_decode(file_get_contents('php://input'), true);
        //判断 邮箱  密码  是否存在
        if (!isset($data['email']) || !isset($data['password'])) {
            echo json_encode(retur('失败', '参数错误', 422));
            exit;
        }
        $arr =  Db::table('user')->where(['email' => $data['email'], 'password' => $data['password']])->find();
        if (!$arr) {
            echo json_encode(retur('失败', '账号或密码错误', 422));
            exit;
        }

        if ($arr && !isset($data['code'])) {
            //判断是否在常用环境登陆
            $ip = getIPPrefix($_SERVER['REMOTE_ADDR']);
            $lastip = getIPPrefix($arr['ip']);
            if ($ip != $lastip) {
                //发送邮件

                mail($data['email'], '波段智投-用户登陆', '登陆');
                exit;
            }
        }

        //判断验证码是否正确
        if (isset($data['code'])) {

            $time = date('Y-m-d H:i:s', strtotime('-5 minutes'));

            $arr =  Db::table('mailcode')->where(['mail' => $data['email'], 'time >=' => $time])->order('id',  'desc')->limit(1)->select();

            if ($arr[0]['code'] != $data['code']) {
                echo json_encode(retur('失败', '验证码错误', 493));
                exit;
            }
        }
        self::getJWT($data['email'], $data['password']);
        //这里 登陆成功


    }

    public function getJWT($username, $password)
    {
        try {
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


            $arr =  Db::table($table)->field('*')->where(['username' => $username])->find();
            if ($arr) {
                // 修改
                $arr =  Db::table($table)->where(['username' => $username])->update(['username' => $username, 'keyid' => $uniqid, 'token' => $token]);
            } else {
                // 添加
                $arr =  Db::table($table)->insert(['username' => $username, 'keyid' => $uniqid, 'token' => $token]);
            }

            if ($arr > 0) {
                $ip = $_SERVER['REMOTE_ADDR'];
                Db::table('user')->where(['email' => $username])->update(['ip' => $ip]);
                echo json_encode(retur('登陆成功', $uniqid));
            } else {
                echo json_encode(retur('失败', '网络拥堵请稍后再试', 9000));
            }
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage();
            $errorLine = $th->getLine();
            echo json_encode(retur('失败', "错误信息：{$errorMessage}，发生在第 {$errorLine} 行。", 9000));
        }
    }

    public function Verifyemail()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        //判断 邮箱  密码  是否存在
        if (!isset($data['email'])) {
            echo json_encode(retur('失败', '请输入邮箱地址', 422));
            exit;
        }
        //判断邮箱是否存在
        $arr =  Db::table('user')->where(['email' => $data['email']])->find();
        if (!$arr) {
            echo json_encode(retur('失败', '邮箱不存在', 495));
            exit;
        }
        //发送验证码

        if (!isset($data['code'])) {
            mail($data['email'], '波段智投-找回密码', '找回密码');
            exit;
        }
        //判断验证码是否正确
        $time = date('Y-m-d H:i:s', strtotime('-20 minutes'));
        $arr =  Db::table('mailcode')->where(['mail' => $data['email'], 'time >=' => $time])->order('id',  'desc')->limit(1)->select();
        if ($arr[0]['code'] != $data['code']) {
            echo json_encode(retur('失败', '验证码错误', 493));
            exit;
        }
        if (!isset($data['password'])) {
            echo json_encode(retur('成功', '验证成功'));
            exit;
        }
        //修改密码
        $arr =  Db::table('user')->where(['email' => $data['email']])->update(['password' => $data['password']]);
        if ($arr) {
            echo json_encode(retur('成功', '修改成功'));
        } else {
            echo json_encode(retur('失败', '未修改任何数据', 422));
        }
    }

    public function userinfo()
    {
        try {
            $data =   self::isvalidateJWT();
            echo json_encode(retur('成功', $data));
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', '网络拥堵请稍后再试', 9000));
        }
    }
}
