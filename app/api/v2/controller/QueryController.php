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

    public function Strategy()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $user = self::isvalidateJWT();

        $Strategy = Db::table('Strategy')->field('*')->where(['userid' => $user['id'], 'Label' => $data['Label'], 'token' => $data['token']])->find();

        if ($Strategy) {


            $Strategy['Strategy'] = json_decode(stripslashes($Strategy['Strategy']), true);
            echo json_encode(retur('成功', $Strategy));
        } else {
            echo json_encode(retur('失败', '添加失败请查看参数', 422));
        }
    }
}
