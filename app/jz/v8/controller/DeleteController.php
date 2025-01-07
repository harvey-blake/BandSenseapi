<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\jz\v8\controller;

use common\jzController;
use Db\Db;
use function common\dump;
use function common\retur;

class  DeleteController extends jzController
{
    public function approve()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $arr = Db::table('approve')->where($data)->delete();
        if ($arr > 0) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', $arr, 422));
        }
    }
}
