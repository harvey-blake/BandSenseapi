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
            $key = '466SioaRMJGXfKESRF8mdGzICXDN4bv21TP2KzFYqx6AFMq3TNCiYNYY9TV6Aq32';
            $secret = 'FKEYTbtFUWKyoIrdeaGEsM1LYximNToGWGhyG5hWgWQOSEqGN4wxCE7h5eCzGqOE';
            $client = new Spot(['key' => $key, 'secret' => $secret]);;
            $response = $client->account();
            //uid   是否允许交易   账户类型
            dump($response);

            echo dump($response->uid, $response->canTrade, $response->accountType);
        } catch (\Binance\Exception\ClientException $th) {

            // dump($th->getMessage()); // 打印异常消息
            echo json_encode(retur('失败', '$key或$secret错误', -2014));
        }
    }
}
