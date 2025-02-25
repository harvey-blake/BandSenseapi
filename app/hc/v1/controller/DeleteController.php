<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\hc\v1\controller;


use Db\Db;
use function common\dump;
use function common\retur;
use common\Controller;
use function common\tgverification;

class  DeleteController extends Controller
{

    public function onaddress()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $hash = tgverification($data['hash']);
        $arr =  Db::table('onaddress')->where(['id' => $data['id'], 'userid' => $hash['id']])->delete();
        if ($arr > 0) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', '没删除任何数据', 404));
        }
    }
}
