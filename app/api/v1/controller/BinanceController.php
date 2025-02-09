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
                $arr[$key]['income'] = Db::table('income')->where(['keyid' => $value['id']])->SUM('income') ?? 0;
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
            if ($timestamp - 2 < $key['lasttime']) {
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
    private function adjustQuantity($quantity, $stepSize)
    {
        // 确保步长为浮点数
        $stepSize = (float)$stepSize;
        $quantity = (float)$quantity;

        // 调整数量，确保符合步长
        $adjustedQuantity = floor($quantity / $stepSize) * $stepSize;

        // 获取步长的小数部分的位数
        $decimalPlaces = strlen(explode('.', (string)$stepSize)[1]);

        // 使用 number_format 并确保不添加千位分隔符
        $number = number_format($adjustedQuantity, $decimalPlaces, '.', '');

        return strpos($number, '.') !== false ? rtrim(rtrim($number, '0'), '.') : $number;
        // 确保调整后的数量保留与步长相同的小数位数
        // return rtrim(number_format($adjustedQuantity, $decimalPlaces, '.', ''), '0');
    }
    private function sell($lastOrder)
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
            if ($timestamp - 2 < $key['lasttime']) {
                echo json_encode(retur('失败', '调用频率过高', 2015));
                exit;
            }


            // 初始化 Binance 客户端
            $client = self::getClient($key['APIKey'], $key['SecretKey']);

            // 获取该策略下的历史订单
            $Historicalorders = Db::table('bnorder')->where(['userid' => $user['id'], 'Strategyid' => $data['Strategyid'], 'state' => 1])->select();

            // 获取最后一个历史订单（准备卖出）
            //可只传这一个
            $lastOrder = $Historicalorders[count($Historicalorders) - 1];
            $response = $client->exchangeInfo(['symbol' => 'BTCUSDT']);
            dump($response);
            $lotSize = array_filter($response['symbols'][0]['filters'], fn($filter) => $filter['filterType'] === 'LOT_SIZE');

            $lotSize = array_values($lotSize); // 获取第一个匹配的过滤器
            // dump($lotSize[0]['stepSize']);

            $decimals = $client->exchangeInfo(
                ['symbol' => $Strategy['token']]

            );
            $baseAssetPrecision = $decimals['symbols'][0]['baseAssetPrecision'];
            $adjustedQuantity = self::adjustQuantity(truncateToPrecision($lastOrder['origQty'], $baseAssetPrecision), $lotSize[0]['stepSize']);

            dump($adjustedQuantity);
            // 创建一个市价卖单
            $response = $client->newOrder(
                $Strategy['token'], // 交易对
                'SELL',             // 卖出
                'MARKET',           // 市价单
                [
                    'quantity' => $adjustedQuantity, // 卖出的数量，保留8位精度

                ]
            );

            // 计算本次交易的手续费总额
            $totalCommission = 0;
            foreach ($response['fills'] as $fill) {
                $commission = (float)$fill['commission']; // 单笔成交的手续费
                $totalCommission += $commission;         // 累加手续费
            }

            // 计算实际获得的金额（扣除手续费）
            $actualgain = $response['cummulativeQuoteQty'] - $totalCommission;

            // 计算本次交易的利润（实际获得金额 - 最后一个订单的累计花费）
            // 利润计算修改

            $profit = 0;
            if ($response['status'] == "EXPIRED") {

                //计算利润
                $profit = $actualgain - $response['executedQty'] * $lastOrder['price'];
            } else {
                $profit = $actualgain - $lastOrder['cummulativeQuoteQty'];
            }



            // 将本次利润记录到数据库
            Db::table('income')->insert([
                'userid' => $user['id'],
                'keyid' => $key['id'],
                'Strategyid' => $Strategy['id'],
                'income' => $profit
            ]);

            // 更新策略的总金额（扣除卖出的金额）  有问题
            $lumsum = $Strategy['lumpsum'] - $actualgain;

            // 更新最后一个订单的状态为已完成（state = 0）
            //如果没卖完的话 不能更新

            if ($response['status'] == "EXPIRED") {
                //这里是出错  更新下数据 下次继续卖  要把实际花费改成0
                //或者利润计算  也是计算购买花费的金额 就是
                // $lastOrder['origQty'] 减去已经卖出的数量  就是没有卖出的
                //修改剩余数量
                //修改购买金额

                Db::table('bnorder')->where(['userid' => $user['id'], 'id' => $lastOrder['id'], 'state' => 1])->update(['origQty' => $lastOrder['origQty'] - $response['executedQty'], 'cummulativeQuoteQty' => $lastOrder['cummulativeQuoteQty'] - $response['executedQty'] * $lastOrder['price']]);

                $Historicalorders = Db::table('bnorder')->where(['userid' => $user['id'], 'Strategyid' => $data['Strategyid'], 'state' => 1])->select();

                Db::table('bnsell')->insert([
                    'token' => $Strategy['token'],
                    'ding' => $response,

                ]);
            } else {
                Db::table('bnorder')->where(['userid' => $user['id'], 'id' => $lastOrder['id'], 'state' => 1])->update(['state' => 0]);
                array_pop($Historicalorders);
            }

            // 计算剩余历史订单的总数量
            $HistoricalordersQty = array_sum(array_column($Historicalorders, 'origQty'));

            // 计算新的总平均价格（总金额 / 总数量）
            $Overallaverageprice = $HistoricalordersQty != 0 ? $lumsum / $HistoricalordersQty : '0';
            $lumsum = $lumsum ?: 0;

            // 更新策略的总均价和总金额
            Db::table('Strategy')->where(['userid' => $user['id'], 'id' => $data['Strategyid']])->update([
                'unitprice' => $Overallaverageprice,
                'lumpsum' => $lumsum
            ]);
        } catch (ClientException $e) {
            preg_match('/\{("code":-?\d+,"msg":"[^"]+")\}/', $e->getMessage(), $matches);
            echo json_encode(retur('失败', json_decode($matches[0]), 2015));
            exit;
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

            $Strategy = Db::table('Strategy')
                ->field('*')
                ->where(['id' => $data['Strategyid'], 'userid' => $user['id']])
                ->find();
            // 获取该策略下的历史订单
            $Historicalorders = Db::table('bnorder')->where(['userid' => $user['id'], 'Strategyid' => $data['Strategyid'], 'state' => 1])->select();

            // 获取最后一个历史订单（准备卖出）
            //可只传这一个
            $lastOrder = $Historicalorders[count($Historicalorders) - 1];
            self::sell($lastOrder);
            $timestamp = time();
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
            // 设置脚本允许在客户端断开连接后继续执行
            $data = json_decode(file_get_contents('php://input'), true);
            $user = self::validateJWT();
            ignore_user_abort(true);
            // 设置脚本的最大执行时间为无限制
            set_time_limit(0);

            $Strategy = Db::table('Strategy')
                ->field('*')
                ->where(['id' => $data['Strategyid'], 'userid' => $user['id']])
                ->find();
            // 获取该策略下的历史订单
            $Historicalorders = Db::table('bnorder')->where(['userid' => $user['id'], 'Strategyid' => $data['Strategyid'], 'state' => 1])->select();

            // 获取最后一个历史订单（准备卖出）
            //可只传这一个
            foreach ($Historicalorders as $key => $value) {
                self::sell($value);
            }



            $newHistoricalorders = Db::table('bnorder')->where(['userid' => $user['id'], 'Strategyid' => $data['Strategyid'], 'state' => 1])->select();
            // 如果没有le重置策略的总金额和平均单价为0
            if (!$newHistoricalorders) {
                Db::table('Strategy')->where(['id' => $data['Strategyid'], 'userid' => $user['id']])->update(['unitprice' => '0', 'lumpsum' => '0']);
            }

            $timestamp = time();
            Db::table('binance_key')->where(['id' => $Strategy['keyid'], 'userid' => $user['id']])->update(['lasttime' => $timestamp]);

            echo json_encode(retur('成功', '成功'));
        } catch (ClientException $e) {
            preg_match('/\{("code":-?\d+,"msg":"[^"]+")\}/', $e->getMessage(), $matches);
            echo json_encode(retur('失败', json_decode($matches[0]), 2015));
        }
    }



    public function orderpol()
    {

        // dump($_SERVER);
        // // try {


        $client = self::getClient('FssGaRMUd3zj3r0WFzCQEiIIgDWtYl2uaj1nNOukKMRYGAwE5sD9nWKWMQ42dm0Q', '7mqzsReNp1Qs02Ou6rZiCtRIDNDRwBpwW8mPHzw1MZk8XEMMZtr5EhRUDCgACwsf');

        $response = $client->exchangeInfo(['symbol' => 'BTCUSDT']);
        dump($response['symbols'][0]['filters']);

        $lotSize = array_filter($response['symbols'][0]['filters'], fn($filter) => $filter['filterType'] === 'LOT_SIZE');
        dump($lotSize['stepSize']);
        // $symbolInfo = $response['symbols'][0];
        // $filters = $symbolInfo['filters'];

        // // 提取 LOT_SIZE 的步长
        // $lotSize = array_filter($filters, fn($filter) => $filter['filterType'] === 'LOT_SIZE');
        // if ($lotSize) {
        //     $lotSize = array_values($lotSize)[0];
        //     echo "数量步长 (stepSize): {$lotSize['stepSize']}\n";
        // }

        //     // 创建市价卖单（卖出总数量）
        //     $response = $client->newOrder(
        //         'ETHUSDT', // 交易对
        //         'SELL',             // 卖出
        //         'MARKET',           // 市价单
        //         [
        //             'quantity' => '1', // 卖出的总数量，保留8位精度

        //         ]
        //     );
        //     dump($response);
        // } catch (ClientException $e) {
        //     preg_match('/\{("code":-?\d+,"msg":"[^"]+")\}/', $e->getMessage(), $matches);
        //     echo json_encode(retur('失败', json_decode($matches[0]), 2015));
        // }
    }
}
