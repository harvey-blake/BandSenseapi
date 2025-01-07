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
use OSS\OssClient;
use OSS\Core\OssException;

use AlibabaCloud\SDK\Sts\V20150401\Sts;
use Darabonba\OpenApi\Models\Config;
use AlibabaCloud\SDK\Sts\V20150401\Models\AssumeRoleRequest;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;

use common\CallbackController;
use function common\dump;




class jzController
{
    protected View $view;
    protected  $myCallback;
    // 构造器
    public function __construct(View $view)
    {

        $this->view = $view;
        $this->myCallback = new CallbackController();
    }


    // 获取JWT  需要测试的
    public function JWT()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $arr = false;
            if (isset($data['username']) && isset($data['password'])) {
                $arr =  Db::table('JZ_user')->field('*')->where($data)->find();
            }
            $userid = '';
            if ($arr) {
                $domain = $arr['domain'];
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
                $builder->withClaim('domain', $domain);
                $builder->withClaim('username', $username);
                $builder->withClaim('password', $password);
                $token = $builder->getToken($config->signer(), $config->signingKey());
                $token = encryptData($token->toString());
                $uniqid = Uuid::uuid4();
                $uniqid = $uniqid->toString();
                $arr =  Db::table('jz_secretKey')->field('secretKey')->where(['user' => $username])->find();
                if ($arr) {
                    // 修改
                    $arr =  Db::table('jz_secretKey')->where(['user' => $username])->update(['id' => $uniqid, 'secretKey' => $token]);
                } else {
                    // 添加
                    $arr =  Db::table('jz_secretKey')->insert(['user' => $username, 'id' => $uniqid, 'secretKey' => $token]);
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
            $arr =  Db::table('JZ_user')->field('*')->where($data)->find();
            unset($arr['password']);
            if ($arr) {
                // unset($array['colour']);
                echo json_encode(retur('成功', $arr));
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
        // $data = $this->model->onfetch('*', 'jz_secretKey', 1, ['id' => $data])['data']['secretKey'];
        $data =  Db::table('jz_secretKey')->field('secretKey')->where(['id' => $data])->find();
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
                $arr = ['domain' => $token->claims()->get('domain'), 'username' => $token->claims()->get('username'), 'password' => $token->claims()->get('password')];

                return $arr;
            }
        } else {
            //账号登陆失效
            echo json_encode(retur('出错了', '授权已过期', 401));
            exit;
        }
    }
    // 用于跨站管理员验证
    public function Crosssitever()
    {
        // 数据库的 API 端点 URL
        $url = 'https://v1.dexc.pro/edu/v2/Query/Crosssiteverification'; // 更改为实际的数据库端点 URL

        // 设置请求头
        $options = array(
            'http' => array(
                'header' => "Authorization:" . $_SERVER['HTTP_AUTHORIZATION'] . "\r\n" . // 替换为你实际的授权令牌
                    "Content-Type: application/json\r\n" // 如果需要设置其他头部，可以在这里添加
            )
        );
        // 创建上下文（context）用于请求
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        $response = json_decode($response, true);
        // dump($response);
        if ($response['code'] == 200 && $response['data']['administrators'] == 1) {

            return true;
        } else {
            echo json_encode(retur('出错了', '非法访问', 498));
            exit;
        }
        // 发起请求并获取响应
        // return file_get_contents($url, false, $context);
    }
    public  function  testandverify()
    {
        $ele = self::validateJWT();
        if ($ele) {
            // dump($ele);
            $arr =  Db::table('JZ_user')->field('*')->where($ele)->find();
            if ($arr) {
                return $arr;
            } else {
                echo json_encode(retur('出错了', '授权已过期', 401));
                exit;
            }
        }
    }
    // 文件上传下载授权  限制了路径  保证了文件的绝对安全性 重复攻击  他也永远上传的是同一个文件
    public function Fileuploadauthorization($path)
    {
        // TYPE 1等于上传   2等于下载  只给一个权限  防止私钥被用作其他
        $accessKeyId = 'LTAI5t7btPWJTiXB6WDqayPq';
        $accessKeySecret = 'R2iHhCWPqXzkGZNxwE6yGzVPR3u2B6';
        try {
            $config = new Config([
                'accessKeyId' => $accessKeyId,
                'accessKeySecret' => $accessKeySecret
            ]);
            $config->endpoint = "sts.cn-beijing.aliyuncs.com";
            $client =  new Sts($config);
            $assumeRoleRequest = new AssumeRoleRequest([
                "roleArn" => "acs:ram::1019247211553594:role/dexcpro",
                "roleSessionName" => "sessiontest",
                "durationSeconds" => 900,
                "policy" => '{
                       "Version": "1",
                       "Statement": [
                         {
                           "Effect": "Allow",
                           "Action": [
                              "oss:PutObject"
                           ],
                           "Resource": "acs:oss:*:*:webgallery/' . $path . '"
                         }
                       ]
                     }'
            ]);
            // dump($path);
            // 下面6行是测试用的 可以删  这个$runtime 表示最多三个链接  10秒内操作有效
            $runtime = new RuntimeOptions([]);
            $response = $client->assumeRole($assumeRoleRequest, $runtime);
            // $result = $client->assumeRoleWithOptions($assumeRoleRequest, $runtime);
            return $response->body->credentials;
        } catch (\Exception $e) {
            dump($e);
            return false;
        }
    }
    function deleteOSSFile($path, $bucket = 'webgallery')
    {
        $accessKeyId = ACCESSKEYID;
        $accessKeySecret = ACCESSKEYSECRET;
        $endpoint = 'http://oss-cn-beijing.aliyuncs.com'; // 例如：'http://oss-cn-beijing.aliyuncs.com'
        $bucketName = $bucket; // 您的OSS存储桶名称
        $objectKey = $path; // 要删除的文件的对象键（路径）
        try {
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);

            // 执行删除操作

            $retr =   $ossClient->deleteObject($bucketName, $objectKey);
            // dump($retr);
            return true;
        } catch (OssException $e) {
            return false;
        }
    }
}
