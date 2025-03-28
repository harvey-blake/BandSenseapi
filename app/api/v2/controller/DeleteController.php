<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\api\v2\controller;


use Db\Db;
use function common\dump;
use function common\retur;
use common\Controller;

class  DeleteController extends Controller
{

    public function Strategy()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $user = self::isvalidateJWT();
        $arr =  Db::table('Strategy')->where(['id' => $data['id'], 'userid' => $user['id']])->delete();
        if ($arr > 0) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', '没删除任何数据', 404));
        }
    }
}
