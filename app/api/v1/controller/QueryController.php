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
            $todasum =  Db::table('income')->where(['userid' => $user['id'], 'time >=' => $todayStart, 'time <=' => $todayEnd])->SUM('income') ?? 0;
            $monthsum =  Db::table('income')->where(['userid' => $user['id'], 'time >=' => $monthStart, 'time <=' => $monthEnd])->SUM('income') ?? 0;
            $pastProfit =  Db::table('income')->where(['userid' => $user['id']])->SUM('income') ?? 0;

            echo json_encode(retur('成功', ['todasum' => $todasum, 'monthsum' => $monthsum, 'pastProfit' => $pastProfit]));
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', '未知错误', 409));
        }
    }
    //查询策略
    public function Strategyt()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $user = self::validateJWT();
        $arr =  Db::table('Strategy')->where(['userid' => $user['id']])->order('time', 'desc')->limit($data['perPage'])->page($data['page'])->select();
        $count =  Db::table('Strategy')->where(['userid' => $user['id']])->count();
        if (count($arr) > 0) {
            foreach ($arr as $key => $value) {
                //子账户
                $arr[$key]['Strategy'] = json_decode(stripslashes($value['Strategy']));
                $arr[$key]['keyname'] =  Db::table('binance_key')->field('Label')->where(['userid' => $user['id'], 'id' => $value['keyid']])->find();
                $arr[$key]['pastProfit'] =  Db::table('income')->where(['userid' => $user['id'], 'Strategyid' =>  $value['id']])->SUM('income') ?? 0;
            }
            echo json_encode(retur($count, $arr));
        } else {
            echo json_encode(retur($count, $arr, 422));
        }
    }

    public function allStrategyt()
    {

        $user = self::validateJWT();
        $arr =  Db::table('Strategy')->where(['userid' => $user['id'], 'state !=' => 0])->select();

        if (count($arr) > 0) {
            foreach ($arr as $key => $value) {
                //子账户
                $arr[$key]['keyname'] =  Db::table('binance_key')->field('Label')->where(['userid' => $user['id'], 'id' => $value['keyid']])->find()['Label'];
                $arr[$key]['Strategy'] = json_decode(stripslashes($arr[$key]['Strategy']), true);
            }
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', $arr, 422));
        }
    }
    public function allStrategytbotfind()
    {

        $data = json_decode(file_get_contents('php://input'), true);
        $user = self::validateJWT();
        $arr =  Db::table('Strategy')->where(['userid' => $user['id'], 'id' => $data['id'], 'state' => 3])->find();
        if ($arr) {
            $arr['Strategy'] = json_decode(stripslashes($arr['Strategy']), true);
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', $arr, 422));
        }
    }
    public function allStrategytbot()
    {

        $user = self::validateJWT();
        $arr =  Db::table('Strategy')->where(['userid' => $user['id'], 'state' => 3])->select();

        if (count($arr) > 0) {
            foreach ($arr as $key => $value) {
                //子账户
                $arr[$key]['Strategy'] = json_decode(stripslashes($arr[$key]['Strategy']), true);
            }
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', $arr, 422));
        }
    }


    //验证码验证
    public function iscode()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $currentTimestamp =  date('Y-m-d H:i:s', time() - 300);

        $state =  Db::table('Emailrecords')->field('*')->where(['mail' => $data['mail'], "code" => $data['code'], 'time >' => $currentTimestamp])->find();
        if ($state) {
            echo json_encode(retur('成功', '成功'));
        } else {
            echo json_encode(retur('失败', '验证码已过期或不存在', 422));
        }
    }
    //获取订单
    public function bnorder()
    {

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $user = self::validateJWT();
            $state =  Db::table('bnorder')->field('*')->where(['Strategyid' => $data['id'], "userid" => $user['id'], 'state' => 1])->select();
            if ($state) {
                foreach ($state as $key => $value) {
                    $state[$key]['orderinfo'] = json_decode(stripslashes($state[$key]['orderinfo']), true);
                }
            }
            echo json_encode(retur('成功', $state));
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', '未知错误', 422));
        }
    }
}
