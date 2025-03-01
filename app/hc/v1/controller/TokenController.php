<?php

namespace app\hc\v1\controller;

use Db\Db;
use function common\dump;
use function common\retur;
use function common\tgverification;
use function common\mnemonic;
use Web3\Web3;
use Web3\Contract;
use common\CallbackController;
use Web3\Providers\HttpAsyncProvider;




use common\Controller;
// 写入
class TokenController extends Controller
{
    public function getchain()
    {
        $arr =  Db::table('chain')->select();

        echo json_encode(retur('成功', $arr));
    }
    public function getaddress()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $arr =  Db::table('onaddress')->where(['token' => $data['token']])->select();
        echo json_encode(retur('成功', $arr));
    }

    public function gettoken() {}
}
