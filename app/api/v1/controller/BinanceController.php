<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\api\v1\controller;



use Db\Db;
use function common\dump;
use function common\retur;
use Binance\Spot;
use common\Controller;

class BinanceController extends Controller
{
    //币安控制器

    // $key = '466SioaRMJGXfKESRF8mdGzICXDN4bv21TP2KzFYqx6AFMq3TNCiYNYY9TV6Aq32';
    // $secret = 'FKEYTbtFUWKyoIrdeaGEsM1LYximNToGWGhyG5hWgWQOSEqGN4wxCE7h5eCzGqOE';

    public function account()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $user = self::validateJWT();


            $client = new Spot(['key' => $data['APIKey'], 'secret' => $data['SecretKey']]);
            $response = $client->account();

            $arr =  Db::table('binance_key')->field('*')->where(['uid' => $response['uid']])->find();
            if (!$arr) {
                //标签  uid   是否允许交易   账户类型
                $arr =  Db::table('binance_key')->insert(['userid' => $user['id'], 'APIKey' => $data['APIKey'], 'SecretKey' => $data['SecretKey'], 'Label' => $data['Label'], 'uid' => $response['uid'], 'canTrade' => $response['canTrade'], 'accountType' => $response['accountType'], 'Balance' => $response['balances']]);

                if ($arr > 0) {
                    echo json_encode(retur('成功', $arr));
                } else {
                    echo json_encode(retur('失败', '添加失败请查看参数', 422));
                }
            } else {
                echo json_encode(retur('失败', '账户已经存在,请更换子账户', 409));
            }
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', '$key或$secret错误', -2014));
        }
    }
    private function updateaccount($userid)
    {
        try {
            $arr =  Db::table('binance_key')->where(['userid' => $userid])->select();

            foreach ($arr as $key => $value) {
                $client = new Spot(['key' => $value['APIKey'], 'secret' => $value['SecretKey']]);
                $response = $client->account();
                $arr =  Db::table('binance_key')->where(['APIKey' => $value['APIKey']])->update(['canTrade' => $response['canTrade'], 'accountType' => $response['accountType'], 'Balance' => $response['balances']]);
            }
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    // 查询账户

    public function getaccount()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $user = self::validateJWT();
        // 更新账户
        self::updateaccount($user['id']);

        $arr =  Db::table('binance_key')->where(['userid' => $user['id']])->order('time', 'desc')->limit($data['perPage'])->page($data['page'])->select();

        $count =  Db::table('binance_key')->where(['userid' => $user['id']])->count();
        if (count($arr) > 0) {
            echo json_encode(retur($count, $arr));
        } else {
            echo json_encode(retur($count, $arr, 422));
        }
        //获取账户

    }
}
