<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\dapp\topup\controller;


use Db\Db;
use function common\dump;
use function common\retur;
use function common\sendMessage;
use function common\Message;
use function common\tgverification;

class  UpdateController
{
    public function  telegram()
    {
        $update = file_get_contents("php://input");
        $update = json_decode($update, true);

        if (isset($update['message'])) {
            $chat_id = $update['message']['chat']['id'];
            $text = $update['message']['text'];

            // 根据收到的消息内容发送回复
            if ($text == "/start") {

                Message($chat_id);
            } elseif ($text == "/price") {
                $price_message = "The current KittyToken price is XYZ."; // Replace $XYZ with actual price data
                sendMessage($chat_id, $price_message);
            } elseif ($text == "/airdrop") {
                $airdrop_message = "Check out our latest airdrop activities!";
                sendMessage($chat_id, $airdrop_message);
            }
        }
    }


    public function UpdateVIP()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $arr =  Db::table('tg_mac')->field('*')->where(['userid' => $data['mac']])->find();
        $ver =  tgverification($data['sing']);
        $Whethertobind =  Db::table('tg_mac')->field('*')->where(['tgid' => $ver['id']])->find();
        if ($ver && $arr && $Whethertobind['admin'] == 1 && $data['day'] <= 31) {
            $time = 0;
            if ($arr['endtime'] > time()) {
                $time = intval($arr['endtime']) + 86400 * $data['day'];
            } else {
                $time = time() + 86400 * $data['day'];
            }
            Db::table('tg_mac')->where(['userid' => $data['mac']])->update(['endtime' =>  $time]);
            echo json_encode(retur('成功', $time));
        } else {

            http_response_code(500);
        }
    }
}
