<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\api\v2\controller;


use Db\Db;
use function common\dump;
use function common\retur;


use common\Controller;

class  UpdateController extends Controller
{

    public function Strategy()
    {

        // 解析前端传来的 JSON 数据
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['token']) || !isset($data['Label']) || !isset($data['Strategy'])) {
            echo json_encode(retur('失败', '非法访问', 422));
            exit;
        }

        // 验证用户身份，获取用户信息（如 id、钱包地址、余额等）
        $user = self::isvalidateJWT();

        $Strategy = Db::table('Strategy')->field('*')->where(['userid' => $user['id'], 'Label' => $data['Label'], 'token' => $data['token']])->find();
        if (!$Strategy) {
            $arr = Db::table('Strategy')->insert(['userid' => $user['id'], 'Label' => $data['Label'], 'token' => $data['token'], 'compoundinterest' => $data['compoundinterest'], 'Strategy' => $data['Strategy']]);
            if ($arr > 0) {
                echo json_encode(retur('成功', '添加成功'));
            } else {
                echo json_encode(retur('失败', '添加失败请查看参数', 422));
            }
        } else {
            dump($data['compoundinterest']);
            $arr =   Db::table('Strategy')->where(['userid' => $user['id'], 'Label' => $data['Label'], 'compoundinterest' => $data['compoundinterest'], 'token' => $data['token']])->update(['Strategy' => $data['Strategy']]);

            if ($arr > 0) {
                echo json_encode(retur('成功', '修改成功'));
            } else {
                echo json_encode(retur('失败', '未修改任何数据', 409));
            }
        }
    }
}
