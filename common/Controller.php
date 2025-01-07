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
    // 视图对象
    protected View $view;
    protected  $myCallback;
    // 构造器
    public function __construct(View $view)
    {

        $this->view = $view;
        $this->myCallback = new CallbackController();
    }
    // 这是公共的控制器  可以写  所有都用的函数 不管哪个版本 都可以访问
    public function chaxun()
    {


        chaxun();
    }
    // 根据用户授权码  获取用户信息
    public  function getUserInfoFromGitHub($code)
    {
        // 您的 GitHub OAuth 应用程序的 Client ID 和 Client Secret
        $clientID = '0282aacf69050a9690fb';
        $clientSecret = 'f12abe03cadf96bc2b1058b873d4b6d431d282db';

        // 指定重定向 URI
        $redirectUri = 'http://localhost:8080/login';

        // 使用授权码交换访问令牌
        $accessTokenUrl = 'https://github.com/login/oauth/access_token';
        $data = array(
            'client_id' => $clientID,
            'client_secret' => $clientSecret,
            'code' => $code,

        );

        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                    "Accept: application/json\r\n", // 添加 Accept 标头
                'content' => http_build_query($data)
            )
        );

        $context = stream_context_create($options);
        $response = file_get_contents($accessTokenUrl, false, $context);
        $params = json_decode($response, true);

        // 检查是否成功获取访问令牌
        if (isset($params['access_token'])) {
            // 获取到了访问令牌，授权码有效，可以通过访问令牌调用 GitHub API 获取用户信息
            $accessToken = $params['access_token'];

            // 使用访问令牌获取用户信息
            $userInfoUrl = 'https://api.github.com/user';
            $options = array(
                'http' => array(
                    'method' => 'GET',
                    'header' => "Authorization: Bearer " . $accessToken . "\r\n" .
                        "User-Agent: Your-App-Name\r\n" // 替换为您的应用程序名称
                )
            );

            $context = stream_context_create($options);
            $userInfo = file_get_contents($userInfoUrl, false, $context);
            $userInfo = json_decode($userInfo, true);
            // 返回用户信息
            return $userInfo['id'];
        } else {
            // 处理获取访问令牌失败的情况
            return null;
        }
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
            } else if (isset($data['message']) && isset($data['signature'])) {
                $ethAddress = ETHverifyMessage($data['message'], $data['signature']);
                if ($ethAddress) {
                    if (strtolower($data['address']) == strtolower($ethAddress)) {
                        $ethAddress = strtolower($ethAddress);
                        $arr =  Db::table('dex_user')->field('*')->where(['address' => $ethAddress])->find();
                        // $arr = $this->model->onfetch('*', 'dex_user', 1, ['address' => $ethAddress]);
                    }
                }
            } else if (isset($data['code'])) {
                $token = self::getUserInfoFromGitHub($data['code']);
                if ($token) {
                    $arr =  Db::table('dex_user')->field('*')->where(['githubid' => $token])->find();
                    // $arr = $this->model->onfetch('*', 'dex_user', 1, ['githubid' => $token]);
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
            if (!$data) {
                echo json_encode(retur('出错了', '账号登录失效', 201));
                exit;
            }
            $arr =  Db::table('dex_user')->field('*')->where($data)->find();
            // $arr = $arr ? $data['secretKey'] : $data;
            // $arr = $this->model->onfetch('*', 'dex_user', 1, $data);
            // $arr = $arr['data'];
            unset($arr['password']);
            if ($arr) {
                $Consumption =   Db::table('dex_record')->field('*')->where(['userid' => $arr['id'], 'type' => 3])->select();
                // $Consumption = $this->model->onfetch('*', 'dex_record', 9, ['userid' => $arr['id'], 'type' => 3])['data'];
                $Consumption =  array_column($Consumption, 'value');
                $sumption = '0';
                foreach ($Consumption as $key => $value) {
                    $sumption = bcadd($sumption, $value, 1);
                }
                $arr['Consumption'] = $sumption;
                $arr['balance'] = floor(strval(($arr['balance'] / 1) * 10000)) / 10000;
                $arr['integral'] = floor(strval(($arr['integral'] / 1) * 10000)) / 10000;
                $array =   Db::table('dex_UserGroup')->field('*')->where(['id' => $arr['UserGroup']])->find();
                // $array =  $this->model->onfetch('*', 'dex_UserGroup', 1, ['id' => $arr['UserGroup']]);
                // $array = $array['data'];
                unset($array['id'], $array['colour']);
                // unset($array['colour']);
                echo json_encode(retur('成功', array_merge($arr, $array)));
            } else {
                echo json_encode(retur('失败', false, 9000));
            }
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', $th, 9000));
        }
    }
    function validateJWT()
    {

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
        // $data = $this->model->onfetch('*', 'dex_secretKey', 1, ['id' => $data])['data']['secretKey'];
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
    // 获取用户信息  仅用于相同数据库
    public  function  testandverify()
    {
        $ele = self::validateJWT();
        if ($ele) {
            // dump($ele);
            $arr =  Db::table('dex_user')->field('*')->where($ele)->find();
            $newarr =  Db::table('dex_UserGroup')->field('*')->where(['id' => $arr['UserGroup']])->find();
            unset($newarr['id']);
            $arr = array_merge($arr, $newarr);
            return $arr;
        }
    }

    public function Crosssiteverification()
    {
        $ele = self::validateJWT();
        if ($ele) {
            // dump($ele);
            $arr =  Db::table('dex_user')->field('*')->where($ele)->find();
            $newarr =  Db::table('dex_UserGroup')->field('*')->where(['id' => $arr['UserGroup']])->find();
            unset($newarr['id']);
            $arr = array_merge($arr, $newarr);
            echo json_encode(retur('成功', $arr));
        }
    }
}







// 通用得到控制器函数都写在这  比如用户登录  注册
//可以将 tuill 和数据库文件 作为基类导入
// 在app 哪里NEW好

// 登录 注册  必须写在这里