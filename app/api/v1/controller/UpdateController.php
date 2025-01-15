<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\api\v1\controller;


use Db\Db;
use function common\dump;
use function common\retur;
use function common\sendMessage;
use function common\Message;
use function common\tgverification;
use common\Controller;

class  UpdateController extends Controller
{
    public function Strategystate()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $user = self::validateJWT();
            $state =  Db::table('Strategy')->field('state')->where(['id' => $data['id'], 'userid' => $user['id']])->find();
            $state = $state ^ "1";
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
}
