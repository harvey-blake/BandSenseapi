<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\api\v1\controller;



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
                echo json_encode(retur('失败', 'APIKey已经存在,请勿重复添加', 409));
            }
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', '$key或$secret错误', -2014));
        }
    }
    //更新账户余额
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
            foreach ($arr as $key => $value) {
                $arr[$key]['Balance'] = json_decode(stripslashes($arr[$key]['Balance']), true);
                $arr[$key]['APIKey'] = substr($arr[$key]['APIKey'], 0, 10) . '...' . substr($arr[$key]['APIKey'], -15);
                unset($arr[$key]['SecretKey']);
                # code...
            }
            echo json_encode(retur($count, $arr));
        } else {
            echo json_encode(retur($count, $arr, 422));
        }
        //获取账户

    }

    public function order()
    {
        // 买入  且计算订单单价
        try {
            // 允许在客户端断开连接后继续执行
            //传入策略ID
            ignore_user_abort(true);
            // 设置脚本的最大执行时间，0 表示不限制
            set_time_limit(0);
            // $data = json_decode(file_get_contents('php://input'), true);
            // $user = self::validateJWT();
            $data = ['Strategyid' => 1, 'keyid' => 1];

            $user = ['id' => 1];
            $Strategy = Db::table('Strategy')->field('*')->where(['id' => $data['Strategyid'], 'userid' => $user['id']])->find();
            $key =  Db::table('binance_key')->field('*')->where(['id' => $Strategy['keyid'], 'userid' => $user['id']])->find();

            //  'baseUri' => 'https://testnet.binance.vision/api'
            $client = new Spot(['key' => $key['APIKey'], 'secret' => $key['SecretKey'], 'baseURL' => 'https://testnet.binance.vision']);

            $Historicalorders =  Db::table('bnorder')->where(['userid' => $user['id'], 'Strategyid' => $data['Strategyid'], 'state' => 1])->select();

            //获取购买金额
            //获取策略


            $goumaicelue = json_decode(stripslashes($Strategy['Strategy']), true);
            dump($goumaicelue[count($Historicalorders)]);
            //买入
            $response = $client->newOrder(
                $Strategy['token'],             // 交易对
                'BUY',                 // 买入
                'MARKET',              // 市价单
                [
                    'quoteOrderQty' => $goumaicelue[count($Historicalorders)]['amout'], // 使用 100 USDT
                ]
            );
            //计算单价
            $totalNetQty = 0; // 本次总净数量
            foreach ($response['fills'] as $fill) {
                $qty = (float)$fill['qty']; // 成交数量
                $commission = (float)$fill['commission']; // 手续费
                // 计算净数量和净花费
                $netQty = $qty - $commission; // 减去手续费后的净数量
                // 累加净数量和净花费
                $totalNetQty += $netQty;
            }
            $Historicalorders =  Db::table('bnorder')->where(['userid' => $user['id'], 'Strategyid' => $data['Strategyid'], 'state' => 1])->select();
            //计算历史数量和金额
            $HistoricalordersQty = array_sum(array_column($Historicalorders, 'origQty'));
            $cummulHistorQty = array_sum(array_column($Historicalorders, 'cummulativeQuoteQty'));

            dump([$HistoricalordersQty, $cummulHistorQty]);
            //总均价
            $Overallaverageprice =  ($cummulHistorQty + $response['cummulativeQuoteQty']) / ($HistoricalordersQty + $totalNetQty);
            $arr =  Db::table('Strategy')->where(['userid' => $user['id'], 'id' => $data['Strategyid']])->update(['unitprice' => $Overallaverageprice]);
            // 计算本单均价
            $actualAveragePrice = $totalNetQty > 0 ? $response['cummulativeQuoteQty'] / $totalNetQty : 0;
            $arr =  Db::table('bnorder')->insert(['Strategyid' => $data['Strategyid'], 'userid' => $user['id'], 'orderId' => $response['orderId'], 'price' => $actualAveragePrice, 'cummulativeQuoteQty' => $response['cummulativeQuoteQty'], 'orderinfo' => $response, 'origQty' => $totalNetQty, 'side' => 'buy', 'state' => 1]);
        } catch (ClientException $e) {
            preg_match('/\{("code":-?\d+,"msg":"[^"]+")\}/', $e->getMessage(), $matches);
            dump(json_decode($matches[0]));
        }
    }

    public function orderT()
    {
        // 买入  且计算订单单价
        try {
            // 允许在客户端断开连接后继续执行
            //传入策略ID
            ignore_user_abort(true);
            // 设置脚本的最大执行时间，0 表示不限制
            set_time_limit(0);
            // $data = json_decode(file_get_contents('php://input'), true);
            // $user = self::validateJWT();
            $data = ['Strategyid' => 1, 'keyid' => 1];

            $user = ['id' => 1];
            $Strategy = Db::table('Strategy')->field('*')->where(['id' => $data['Strategyid'], 'userid' => $user['id']])->find();
            $key =  Db::table('binance_key')->field('*')->where(['id' => $Strategy['keyid'], 'userid' => $user['id']])->find();

            //  'baseUri' => 'https://testnet.binance.vision/api'
            $client = new Spot(['key' => $key['APIKey'], 'secret' => $key['SecretKey'], 'baseURL' => 'https://testnet.binance.vision']);

            $Historicalorders =  Db::table('bnorder')->where(['userid' => $user['id'], 'Strategyid' => $data['Strategyid'], 'state' => 1])->select();

            //卖出最后一个

            dump($Historicalorders[count($Historicalorders) - 1]);
            //卖出
            $response = $client->newOrder(
                $Strategy['token'],             // 交易对
                'SELL',                 // 买入
                'MARKET',              // 市价单
                [
                    'quoteOrderQty' => truncateToPrecision($Historicalorders[count($Historicalorders) - 1]['origQty'], 8)  // 使用 100 USDT
                ]
            );
            //卖出得到多少钱  然后减去手续费  最终得到的金额  减去 购买的金额   就等于利润
            //然后 历史的购买金额 去掉最后一个  减去利润 除以数量  得到最新的均价
            dump($response);
        } catch (ClientException $e) {
            preg_match('/\{("code":-?\d+,"msg":"[^"]+")\}/', $e->getMessage(), $matches);
            dump(json_decode($matches[0]));
        }
    }
}
