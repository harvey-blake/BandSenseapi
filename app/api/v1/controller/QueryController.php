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
    public function Profit()
    {
        try {
            $user = self::validateJWT();
            $todayStart = date('Y-m-d 00:00:00');  // 今日开始时间
            $todayEnd = date('Y-m-d 23:59:59');    // 今日结束时间
            $monthStart = date('Y-m-01 00:00:00');  // 本月开始时间
            $monthEnd = date('Y-m-t 23:59:59');     // 本月结束时间
            $todasum =  Db::table('Strategy')->where(['userid' => $user['id'], 'time >=' => $todayStart, 'time <=' => $todayEnd])->SUM('unitprice') ?? 0;
            $monthsum =  Db::table('Strategy')->where(['userid' => $user['id'], 'time >=' => $monthStart, 'time <=' => $monthEnd])->SUM('unitprice') ?? 0;
            $pastProfit =  Db::table('Strategy')->where(['userid' => $user['id']])->SUM('unitprice') ?? 0;

            echo json_encode(retur('成功', ['todasum' => $todasum, 'monthsum' => $monthsum, 'pastProfit' => $pastProfit]));
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', '未知错误', 409));
        }
    }
}
