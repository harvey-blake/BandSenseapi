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

    private function getClient($APIKey, $SecretKey)
    {
        try {
            return new Spot(['key' => $APIKey, 'secret' => $SecretKey, 'baseURL' => 'https://testnet.binance.vision']);
        } catch (\Throwable $th) {
            return null;
        }
    }
    public function account()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $user = self::validateJWT();

            $client = self::getClient($data['APIKey'], $data['SecretKey']);
            // $client = new Spot(['key' => $data['APIKey'], 'secret' => $data['SecretKey']]);
            $response = $client->account();

            $arr =  Db::table('binance_key')->field('*')->where(['APIKey' => $data['APIKey']])->find();
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
            dump($th);
            echo json_encode(retur('失败', '$key或$secret错误', -2014));
        }
    }
    //更新账户余额
    private function updateaccount($userid)
    {
        try {
            $arr =  Db::table('binance_key')->where(['userid' => $userid])->select();

            foreach ($arr as $key => $value) {
                $client = self::getClient($value['APIKey'], $value['SecretKey']);

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
        // 买入并计算订单单价
        try {
            // 设置脚本允许在客户端断开连接后继续执行
            $data = json_decode(file_get_contents('php://input'), true);
            $user = self::validateJWT();
            ignore_user_abort(true);
            // 设置脚本的最大执行时间，0 表示不限制
            set_time_limit(0);
            // 模拟数据，用于获取策略ID和密钥ID


            // 获取用户的策略信息
            $Strategy = Db::table('Strategy')->field('*')->where(['id' => $data['Strategyid'], 'userid' => $user['id']])->find();
            // 获取用户的API密钥信息
            $key = Db::table('binance_key')->field('*')->where(['id' => $Strategy['keyid'], 'userid' => $user['id']])->find();


            //查新   上次调用距离现在没到60秒 禁止调用
            $timestamp = time();
            if ($timestamp - 60 < $key['lasttime']) {
                echo json_encode(retur('失败', '调用频率过高', 2015));
                exit;
            }

            // 初始化 Binance 客户端
            $client = self::getClient($key['APIKey'], $key['SecretKey']);

            $Historicalorders = Db::table('bnorder')->where(['userid' => $user['id'], 'Strategyid' => $data['Strategyid'], 'state' => 1])->select();

            // 从策略中获取当前的购买设置
            $goumaicelue = json_decode(stripslashes($Strategy['Strategy']), true);
            // 创建一个市价买单
            $response = $client->newOrder(
                $Strategy['token'], // 交易对
                'BUY',              // 买入
                'MARKET',           // 市价单
                [
                    'quoteOrderQty' => $goumaicelue[count($Historicalorders)]['amout'], // 使用指定金额

                ]
            );

            // 计算本次订单的净数量（使用 BCMath）
            $totalNetQty = '0';
            foreach ($response['fills'] as $fill) {
                $qty = $fill['qty'];             // 成交数量（字符串格式）
                $commission = $fill['commission']; // 手续费（字符串格式）
                $netQty = bcsub($qty, $commission, 8); // 计算净数量，保留8位小数
                $totalNetQty = bcadd($totalNetQty, $netQty, 8); // 累加净数量
            }

            // 计算历史订单的总数量和总金额（使用 BCMath）
            $HistoricalordersQty = '0';
            $cummulHistorQty = '0';
            foreach ($Historicalorders as $order) {
                $HistoricalordersQty = bcadd($HistoricalordersQty, $order['origQty'], 8);
                $cummulHistorQty = bcadd($cummulHistorQty, $order['cummulativeQuoteQty'], 8);
            }

            // 计算总平均价格（使用 BCMath）
            $Overallaverageprice = bcdiv(
                bcadd($cummulHistorQty, $response['cummulativeQuoteQty'], 8),
                bcadd($HistoricalordersQty, $totalNetQty, 8),
                8
            );

            // 更新策略信息，包括总金额和总均价
            Db::table('Strategy')->where(['userid' => $user['id'], 'id' => $data['Strategyid']])
                ->update([
                    'unitprice' => $Overallaverageprice,
                    'lumpsum' => bcadd($Strategy['lumpsum'], $response['cummulativeQuoteQty'], 8)
                ]);

            // 计算本单的实际平均价格（使用 BCMath）
            $actualAveragePrice = bccomp($totalNetQty, '0', 8) > 0
                ? bcdiv($response['cummulativeQuoteQty'], $totalNetQty, 8)
                : '0';

            // 插入订单信息
            Db::table('bnorder')->insert([
                'Strategyid' => $data['Strategyid'],
                'userid' => $user['id'],
                'orderId' => $response['orderId'],
                'price' => $actualAveragePrice,
                'cummulativeQuoteQty' => $response['cummulativeQuoteQty'],
                'orderinfo' => $response,
                'origQty' => $totalNetQty,
                'side' => 'buy',
                'state' => 1
            ]);
            Db::table('binance_key')->where(['id' => $Strategy['keyid'], 'userid' => $user['id']])->update(['lasttime' => $timestamp]);

            echo json_encode(retur('成功', '成功'));
        } catch (ClientException $e) {
            preg_match('/\{("code":-?\d+,"msg":"[^"]+")\}/', $e->getMessage(), $matches);
            echo json_encode(retur('失败', json_decode($matches[0]), 2015));
        }
    }



    public function orderT()
    {
        try {
            // 设置脚本允许在客户端断开连接后继续执行
            $data = json_decode(file_get_contents('php://input'), true);
            $user = self::validateJWT();
            ignore_user_abort(true);
            // 设置脚本的最大执行时间为无限制
            set_time_limit(0);



            // 从数据库中获取策略信息
            $Strategy = Db::table('Strategy')->field('*')->where(['id' => $data['Strategyid'], 'userid' => $user['id']])->find();
            // 从数据库中获取API密钥信息
            $key = Db::table('binance_key')->field('*')->where(['id' => $Strategy['keyid'], 'userid' => $user['id']])->find();

            //查新   上次调用距离现在没到60秒 禁止调用
            $timestamp = time();
            if ($timestamp - 20 < $key['lasttime']) {
                echo json_encode(retur('失败', '调用频率过高', 2015));
                exit;
            }


            // 初始化 Binance 客户端
            $client = self::getClient($key['APIKey'], $key['SecretKey']);

            // 获取该策略下的历史订单
            $Historicalorders = Db::table('bnorder')->where(['userid' => $user['id'], 'Strategyid' => $data['Strategyid'], 'state' => 1])->select();

            // 获取最后一个历史订单（准备卖出）
            $lastOrder = $Historicalorders[count($Historicalorders) - 1];

            // 创建一个市价卖单
            $response = $client->newOrder(
                $Strategy['token'], // 交易对
                'SELL',             // 卖出
                'MARKET',           // 市价单
                [
                    'quantity' => truncateToPrecision($lastOrder['origQty'], 8), // 卖出的数量，保留8位精度

                ]
            );

            // 计算本次交易的手续费总额
            $totalCommission = 0;
            foreach ($response['fills'] as $fill) {
                $commission = (float)$fill['commission']; // 单笔成交的手续费
                $totalCommission += $commission;         // 累加手续费
            }

            // 计算实际获得的金额（扣除手续费）
            $dedao = $response['cummulativeQuoteQty'] - $totalCommission;

            // 计算本次交易的利润（实际获得金额 - 最后一个订单的累计花费）
            $lirun = $dedao - $lastOrder['cummulativeQuoteQty'];

            // 将本次利润记录到数据库
            Db::table('income')->insert([
                'userid' => $user['id'],
                'keyid' => $key['id'],
                'Strategyid' => $Strategy['id'],
                'income' => $lirun
            ]);

            // 更新策略的总金额（扣除卖出的金额）
            $lumsum = $Strategy['lumpsum'] - $dedao;

            // 更新最后一个订单的状态为已完成（state = 0）
            //如果没卖完的话 不能更新

            if ($response['status'] == "EXPIRED") {
                //这里是出错  更新下数据 下次继续卖  要把实际花费改成0
                //或者利润计算  也是计算购买花费的金额 就是
                // $lastOrder['origQty'] 减去已经卖出的数量  就是没有卖出的
                Db::table('token')->insert([
                    'token' => $Strategy['token'],
                    'ding' => $response,

                ]);
            } else {
                Db::table('bnorder')->where(['userid' => $user['id'], 'id' => $lastOrder['id'], 'state' => 1])->update(['state' => 0]);
            }



            // 删除最后一个历史订单记录（在内存中）
            array_pop($Historicalorders);

            // 计算剩余历史订单的总数量
            $HistoricalordersQty = array_sum(array_column($Historicalorders, 'origQty'));

            // 计算新的总平均价格（总金额 / 总数量）
            $Overallaverageprice = $HistoricalordersQty != 0 ? $lumsum / $HistoricalordersQty : '0';
            $lumsum = $HistoricalordersQty ?: 0;

            // 更新策略的总均价和总金额
            Db::table('Strategy')->where(['userid' => $user['id'], 'id' => $data['Strategyid']])->update([
                'unitprice' => $Overallaverageprice,
                'lumpsum' => $lumsum
            ]);
            Db::table('binance_key')->where(['id' => $Strategy['keyid'], 'userid' => $user['id']])->update(['lasttime' => $timestamp]);


            echo json_encode(retur('成功', '成功'));
        } catch (ClientException $e) {
            preg_match('/\{("code":-?\d+,"msg":"[^"]+")\}/', $e->getMessage(), $matches);
            echo json_encode(retur('失败', json_decode($matches[0]), 2015));
        }
    }

    public function ordersell()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $user = self::validateJWT();
            // 设置脚本允许在客户端断开连接后继续执行
            ignore_user_abort(true);
            // 设置脚本的最大执行时间为无限制
            set_time_limit(0);

            // 从数据库中获取策略信息
            $Strategy = Db::table('Strategy')
                ->field('*')
                ->where(['id' => $data['Strategyid'], 'userid' => $user['id']])
                ->find();




            // 从数据库中获取API密钥信息
            $key = Db::table('binance_key')
                ->field('*')
                ->where(['id' => $Strategy['keyid'], 'userid' => $user['id']])
                ->find();
            //查新   上次调用距离现在没到60秒 禁止调用
            $timestamp = time();
            if ($timestamp - 60 < $key['lasttime']) {
                echo json_encode(retur('失败', '调用频率过高', 2015));
                exit;
            }

            // 初始化 Binance 客户端
            $client = self::getClient($key['APIKey'], $key['SecretKey']);

            // 获取该策略的所有历史订单
            $Historicalorders = Db::table('bnorder')->where(['userid' => $user['id'], 'Strategyid' => $data['Strategyid'], 'state' => 1])->select();

            // 计算所有历史订单的总交易数量
            $HistoricalordersQty = array_sum(array_column($Historicalorders, 'origQty'));

            // 创建市价卖单（卖出总数量）
            $response = $client->newOrder(
                $Strategy['token'], // 交易对
                'SELL',             // 卖出
                'MARKET',           // 市价单
                [
                    'quantity' => truncateToPrecision($HistoricalordersQty, 8), // 卖出的总数量，保留8位精度

                ]
            );

            // 计算本次交易的手续费总额
            $totalCommission = 0;
            foreach ($response['fills'] as $fill) {
                $commission = (float)$fill['commission']; // 单笔成交的手续费
                $totalCommission += $commission;         // 累加手续费
            }
            //出现的问题 可能根本没卖完 这个要解决  买入 也是

            // 计算实际获得的金额（扣除手续费）
            $dedao = $response['cummulativeQuoteQty'] - $totalCommission;

            // 计算历史订单累计的总花费
            $cummulHistorQty = array_sum(array_column($Historicalorders, 'cummulativeQuoteQty'));

            // 计算利润（实际获得金额 - 历史订单累计花费），利润可能为负数
            $lirun = $dedao - $cummulHistorQty;

            // 将本次利润记录到数据库
            Db::table('income')->insert(['userid' => $user['id'], 'keyid' => $key['id'], 'Strategyid' => $Strategy['id'], 'income' => $lirun,]);

            // 更新所有历史订单的状态为已完成（state = 0）
            Db::table('bnorder')->where(['userid' => $user['id'], 'Strategyid' => $data['Strategyid'], 'state' => 1])->update(['state' => '0']);

            // 重置策略的总金额和平均单价为0
            Db::table('Strategy')->where(['id' => $data['Strategyid'], 'userid' => $user['id']])->update(['unitprice' => '0', 'lumpsum' => '0']);
            Db::table('binance_key')->where(['id' => $Strategy['keyid'], 'userid' => $user['id']])->update(['lasttime' => $timestamp]);

            echo json_encode(retur('成功', '成功'));
        } catch (ClientException $e) {
            preg_match('/\{("code":-?\d+,"msg":"[^"]+")\}/', $e->getMessage(), $matches);
            echo json_encode(retur('失败', json_decode($matches[0]), 2015));
        }
    }

    public function orderpol()
    {
        try {


            $client = self::getClient('FssGaRMUd3zj3r0WFzCQEiIIgDWtYl2uaj1nNOukKMRYGAwE5sD9nWKWMQ42dm0Q', '7mqzsReNp1Qs02Ou6rZiCtRIDNDRwBpwW8mPHzw1MZk8XEMMZtr5EhRUDCgACwsf');



            // 创建市价卖单（卖出总数量）
            $response = $client->newOrder(
                'ETHUSDT', // 交易对
                'SELL',             // 卖出
                'MARKET',           // 市价单
                [
                    'quantity' => '1', // 卖出的总数量，保留8位精度

                ]
            );
            dump($response);
        } catch (ClientException $e) {
            preg_match('/\{("code":-?\d+,"msg":"[^"]+")\}/', $e->getMessage(), $matches);
            echo json_encode(retur('失败', json_decode($matches[0]), 2015));
        }
    }
}
