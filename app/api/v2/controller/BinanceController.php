<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\api\v2\controller;



use Db\Db;
use function common\dump;
use function common\truncateToPrecision;
use function common\retur;
use Binance\Spot;
use common\Controller;
use Binance\Exception\ClientException;
use GuzzleHttp\Exception\ClientException as GuzzleClientException;



class BinanceController extends Controller
{
    //币安控制器
    private function getClient($APIKey, $SecretKey)
    {
        try {
            return new Spot(['key' => $APIKey, 'secret' => $SecretKey]);
            // return new Spot(['key' => $APIKey, 'secret' => $SecretKey, 'baseURL' => 'https://testnet.binance.vision']);
        } catch (\Throwable $th) {
            return null;
        }
    }
    public function account()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $user =   self::isvalidateJWT();


            $client = self::getClient($data['APIKey'], $data['SecretKey']);

            $response = $client->account(
                [
                    'omitZeroBalances' => true, // 隐藏零余额
                ]
            );

            $arr =  Db::table('cexkey')->field('*')->where(['userid' => $user['id'], 'Label' => $data['Label']])->find();
            if (!$arr) {

                $arr =  Db::table('cexkey')->insert(['userid' => $user['id'], 'APIKey' => $data['APIKey'], 'SecretKey' => $data['SecretKey'], 'Label' => $data['Label'], 'uid' => $response['uid'], 'canTrade' => $response['canTrade'], 'accountType' => $response['accountType'], 'Balance' => $response['balances']]);

                if ($arr > 0) {
                    echo json_encode(retur('成功', $arr));
                } else {
                    echo json_encode(retur('失败', '添加失败请查看参数', 422));
                }
            } else {
                echo json_encode(retur('失败', 'APIKey已经存在,请勿重复添加', 409));
            }
        } catch (\Throwable $th) {
            dump($th);
            echo json_encode(retur('失败', 'key或secret错误', -2014));
        }
    }
    //更新账户余额
    private function updateaccount($userid)
    {
        try {
            $arr =  Db::table('cexkey')->where(['userid' => $userid])->select();

            foreach ($arr as $key => $value) {
                $client = self::getClient($value['APIKey'], $value['SecretKey']);

                $response = $client->account([
                    'omitZeroBalances' => true, // 隐藏零余额
                ]);
                $arr =  Db::table('cexkey')->where(['APIKey' => $value['APIKey']])->update(['canTrade' => $response['canTrade'], 'accountType' => $response['accountType'], 'Balance' => $response['balances']]);
            }
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    // 查询账户

    public function getaccount()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $user =   self::isvalidateJWT();
        // 更新账户
        self::updateaccount($user['id']);

        $arr =  Db::table('cexkey')->where(['userid' => $user['id'], 'Label' => $data['Label']])->find();
        if ($arr) {
            $arr['Balance'] = json_decode(stripslashes($arr['Balance']), true);
            $arr['APIKey'] = substr($arr['APIKey'], 0, 10) . '...' . substr($arr['APIKey'], -15);
            unset($arr['SecretKey']);
            # code...
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('错误', $arr, 422));
        }
        //获取账户

    }
    public function getorder()
    {

        //根据代币  用户ID
        $data = json_decode(file_get_contents('php://input'), true);
        $user = self::isvalidateJWT();

        $arr =  Db::table('bnorder')->where(['userid' => $user['id'], 'symbol' => $data['symbol'], 'tab' => $data['tab']])->order('time', 'desc')->limit($data['perPage'])->page($data['page'])->select();

        $count =  Db::table('bnorder')->where(['userid' => $user['id']])->count();

        if (count($arr) > 0) {
            foreach ($arr as $key => $value) {
                $arr[$key]['orderinfo'] = json_decode(stripslashes($arr[$key]['orderinfo']), true);
                $arr[$key]['exchangeInfo'] = Db::table('token')->field(['baseAsset', 'quoteAsset'])->where(['symbol' => $data['symbol']])->find();
            }
            echo json_encode(retur($count, $arr));
        } else {
            echo json_encode(retur($count, $arr, 422));
        }
    }
}
