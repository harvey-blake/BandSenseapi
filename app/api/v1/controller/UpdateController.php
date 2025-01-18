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

    public function subscription()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $template_path = __DIR__ . '/../mail/subscription.html'; // 替换为模板文件的实际路径

        // 生成一个 6 位数字验证码
        $verificationCode = rand(100000, 999999);

        $template_content = file_get_contents($template_path);

        // 替换模板中的验证码（假设验证码使用 {code} 占位符）
        $htmlContent = str_replace('{code}', $verificationCode, $template_content);
        self::mail('3005779@qq.com', '恭喜您订阅成功', $htmlContent);
        // echo json_encode(retur('成功', self::mail($data['mail'], '恭喜您,订阅成功', $template_content)));
    }
}
