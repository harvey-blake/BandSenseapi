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

}
