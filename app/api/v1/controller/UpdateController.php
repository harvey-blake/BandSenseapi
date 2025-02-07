<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\api\v1\controller;


use Db\Db;
use function common\dump;
use function common\retur;


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
    public function Strategy()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $user = self::validateJWT();
            $arr =  Db::table('Strategy')->where(['id' => $data['id'], 'userid' => $user['id']])->update(['Strategy' => $data['Strategy']]);
            if ($arr > 0) {
                echo json_encode(retur('成功', $arr));
            } else {
                echo json_encode(retur('失败', '没更改任何数据', 409));
            }
        } catch (\Throwable $th) {

            echo json_encode(retur('失败', '非法访问', 500));
        }
    }

    public function Retrievepassword()
    {

        $data = json_decode(file_get_contents('php://input'), true);
        $arr =  Db::table('cex_user')->field('*')->where(['username' => $data['mail']])->find();
        if (!$arr) {
            echo json_encode(retur('失败', '用户不存在', 409));
            exit;
        }
        if ($data['mail'] && !isset($data['code'])) {
            self::subscription();
            exit;
        }
        if ($data['mail'] && $data['code'] && $data['Password']) {
            //正式修改密码
            $currentTimestamp =  date('Y-m-d H:i:s', time() - 300);

            $state =  Db::table('Emailrecords')->field('*')->where(['mail' => $data['mail'], "code" => $data['code'], 'time >' => $currentTimestamp])->find();
            if (!$state) {
                echo json_encode(retur('失败', '验证码错误或者已过有效期', 409));
                exit;
            }

            $arr =  Db::table('cex_user')->where(['username' => $data['mail']])->update(['password' => $data['Password']]);
            if ($arr > 0) {
                echo json_encode(retur('成功', $arr));
            } else {
                echo json_encode(retur('失败', '与上次密码相同', 409));
            }
        }
    }

    // 输入邮箱 获取验证码


    //获取验证码
    public function subscription()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $currentTimestamp =  date('Y-m-d H:i:s', time() - 60);
        $ip = $_SERVER['REMOTE_ADDR'];
        $mail = $data['mail'];
        $state =  Db::table('Emailrecords')->field('*')->where(['mail' => $mail,  'time >' => $currentTimestamp])->find();
        $states =  Db::table('Emailrecords')->field('*')->where(['AccessIP' => $ip,  'time >' => $currentTimestamp])->find();
        if ($state || $states) {
            echo json_encode(retur('失败', '60秒只能获取一次验证码', 500));
            exit;
        }
        $verificationCode = rand(100000, 999999);
        $text = '找回密码';
        if (isset($data['type']) && $data['type'] == 'reg') {
            $verificationCode = rand(1000, 9999);
            $text = '注册';
        }

        Db::table('Emailrecords')->insert(['mail' => $mail, 'code' => $verificationCode, 'AccessIP' => $ip]);
        $template_path = __DIR__ . '/../mail/subscription.html'; // 替换为模板文件的实际路径
        // 生成一个 6 位数字验证码
        $template_content = file_get_contents($template_path);
        // 替换模板中的验证码（假设验证码使用 {code} 占位符）
        $htmlContent = str_replace('{code}', $verificationCode, $template_content);
        $htmlContent = str_replace('{text}', $text, $htmlContent);
        self::mail($mail, '波段智投[' . $text . ']', $htmlContent);
        echo json_encode(retur('成功', '获取成功'));
    }
}
