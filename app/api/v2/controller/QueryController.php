<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\api\v2\controller;



use Db\Db;
use function common\dump;
use function common\retur;
use common\Controller;

class QueryController extends Controller
{
    public function too()
    {
        echo '通过';
    }
    public function tokenlist()
    {
        $arr =  Db::table('token')->select();
        if (count($arr) > 0) {

            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', $arr, 422));
        }
    }
}
