<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\api\v1\controller;



use Db\Db;
use function common\dump;
use function common\retur;
use common\Controller;

class QueryController extends Controller
{
    public function tokenlist()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $user = self::validateJWT();
        $arr =  Db::table('tokenlist')->where(['id' => $user['id']])->order('time', 'desc')->limit($data['perPage'])->page($data['page'])->select();
        $count =  Db::table('tokenlist')->where(['id' => $user['id']])->count();
        if (count($arr) > 0) {
            echo json_encode(retur($count, $arr));
        } else {
            echo json_encode(retur($count, $arr, 422));
        }
    }
    public function Timestamp()
    {
        $currentDateTime = date('Y-m-d H:i:s');
        dump($currentDateTime);
        $count =  Db::table('Strategy')->where(['time <' => $currentDateTime])->select();
        dump($count);
        $count =  Db::table('Strategy')->where(['time >' => $currentDateTime])->select();
        dump($count);
    }
}
