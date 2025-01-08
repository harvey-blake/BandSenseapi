<?php

// 控制器类的基类 相当于所有控制器的祖先

namespace common;

use Db\Db;
// use common\CallbackController;
use view\View;
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
            $arr = false;
            if (isset($data['username']) && isset($data['password'])) {
                $arr =  Db::table('dex_user')->field('*')->where($data)->find();
                // $arr = $this->model->onfetch('*', 'dex_user', 1, $data);
            } else if (isset($data['code'])) {
                $token = self::getUserInfoFromGitHub($data['code']);
                if ($token) {
                    $arr =  Db::table('dex_user')->field('*')->where(['githubid' => $token])->find();
                }
            }
            $userid = '';
            if ($arr) {

                $username = $arr['username'];
                $password = $arr['password'];
                $userid = $arr['id'];
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
                $arr =  Db::table('dex_secretKey')->field('secretKey')->where(['user' => $username])->find();
                if ($arr) {
                    // 修改
                    $arr =  Db::table('dex_secretKey')->where(['user' => $username])->update(['id' => $uniqid, 'secretKey' => $token]);
                } else {
                    // 添加
                    $arr =  Db::table('dex_secretKey')->insert(['user' => $username, 'id' => $uniqid, 'secretKey' => $token]);
                }

                if ($arr > 0) {
                    echo json_encode(retur($userid, $uniqid));
                } else {
                    echo json_encode(retur('失败', '网络拥堵请稍后再试', 9000));
                }
            } else {
                echo json_encode(retur('失败', $data, 9000));
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

            return true;
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', $th, 9000));
        }
    }


    // 验证JWT
    function validateJWT()
    {
        return 1;
        // 解密
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        // $headers = getallheaders();
        $data = $_SERVER['HTTP_AUTHORIZATION'];
        // $data = $_GET['token'];
        if (!$data) {
            // 账号未登录
            echo json_encode(retur('错误', '账号未登陆', 403));
            exit;
        }
        $data =  Db::table('dex_secretKey')->field('secretKey')->where(['id' => $data])->find();
        $data = $data ? $data['secretKey'] : $data;
        // 获取数据库
        if ($data) {
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
                $arr = ['username' => $token->claims()->get('username'), 'password' => $token->claims()->get('password')];

                return $arr;
            }
        } else {
            //账号登陆失效
            echo json_encode(retur('出错了', '授权已过期', 401));
            exit;
        }
    }
}
