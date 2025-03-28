<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\api\v2\controller;



use Db\Db;
use function common\dump;
use function common\retur;
use common\Controller;

class QueryController extends Controller
{

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

    public function Strategyall()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $user = self::isvalidateJWT();
        $Strategy = Db::table('Strategy')->field('*')->where(['userid' => $user['id'], 'Label' => $data['Label']])->select();
        if (count($Strategy) > 0) {
            foreach ($Strategy as $key => $value) {
                $Strategy[$key]['income'] = Db::table('income')->where(['Strategyid' => $value['id']])->SUM('income') ?? 0;
            }
            echo json_encode(retur('成功',  $Strategy));
        } else {
            echo json_encode(retur('失败', '无数据', 422));
        }
    }

    public function Profit()
    {
        try {
            $user = self::isvalidateJWT();
            $todayStart = date('Y-m-d 00:00:00');  // 今日开始时间
            $todayEnd = date('Y-m-d 23:59:59');    // 今日结束时间
            $monthStart = date('Y-m-01 00:00:00');  // 本月开始时间
            $monthEnd = date('Y-m-t 23:59:59');     // 本月结束时间
            $todasum =  Db::table('income')->where(['userid' => $user['id'], 'time >=' => $todayStart, 'time <=' => $todayEnd])->SUM('income') ?? 0;
            $monthsum =  Db::table('income')->where(['userid' => $user['id'], 'time >=' => $monthStart, 'time <=' => $monthEnd])->SUM('income') ?? 0;
            $pastProfit =  Db::table('income')->where(['userid' => $user['id']])->SUM('income') ?? 0;

            echo json_encode(retur('成功', ['todasum' => $todasum, 'monthsum' => $monthsum, 'pastProfit' => $pastProfit]));
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', '未知错误', 409));
        }
    }
}
