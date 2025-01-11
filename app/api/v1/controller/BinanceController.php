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
    public function account()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $user = self::validateJWT();
            $key = '466SioaRMJGXfKESRF8mdGzICXDN4bv21TP2KzFYqx6AFMq3TNCiYNYY9TV6Aq32y';
            $secret = 'FKEYTbtFUWKyoIrdeaGEsM1LYximNToGWGhyG5hWgWQOSEqGN4wxCE7h5eCzGqOE';
            $client = new Spot(['key' => $key, 'secret' => $secret]);;
            $response = $client->account();
            //uid   是否允许交易   账户类型
            echo ($response);

            // echo dump($response->uid, $response->canTrade, $response->accountType);
        } catch (\Binance\Exception\ClientException $th) {
            // 打印异常的类型和消息
            dump(get_class($th)); // 打印异常的完整类名
            dump($th->getMessage()); // 打印异常消息

            // 获取响应体并解析错误的 JSON 数据
            $errorDetails = json_decode($th->getMessage(), true);;

            // 打印解析后的错误数据
            dump($errorDetails);  // 输出 {"code": -2014, "msg": "API-key format invalid."}
        }
    }
}
