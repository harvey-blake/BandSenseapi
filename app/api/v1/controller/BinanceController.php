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
        } catch (\Throwable $th) {
            // 打印出异常的消息
            dump($th->getMessage());

            // 如果是客户端异常，获取完整的错误信息
            if ($th instanceof \GuzzleHttp\Exception\ClientException) {
                // 获取响应体内容
                $errorBody = $th->getResponse()->getBody()->getContents();
                dump($errorBody);  // 输出错误的 JSON 响应体
            }
        }
    }
}
