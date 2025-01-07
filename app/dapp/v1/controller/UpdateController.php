<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\dapp\v1\controller;


use Db\Db;
use function common\dump;
use function common\retur;
use function common\sendMessage;
use function common\Message;

class  UpdateController
{
    public function integral()
    {
        // 这个还要判断是否超过两小时
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                echo json_encode(retur('失败', '非法访问', 422));
                exit;
            }
            $user =  Db::table('tg_user')->field('*')->where(['tgid' => $data])->find();
            // 这里判断时间可以领取的时间
            // 当前时间>数据库的时间戳加上86,400,000
            if ($user) {
                $integral = 10;
                $newintegral = $user['integral'] + $integral;
                $Collectiontime = round(microtime(true) * 1000);
                if ($Collectiontime < $user['Collectiontime'] + 43200000) {
                    echo json_encode(retur('失败', '未到领取时间', 422));
                    exit;
                }
                // 这里还要更新领取时间
                Db::table('tg_user')->where(['tgid' => $data])->update(['integral' => $newintegral, 'Collectiontime' => $Collectiontime]);
                Db::table('tg_Record')->insert(['user' => $user['tgid'], 'type' => 'receive', 'amount' => $integral, 'source' => '0', 'method' => 'add']);
                // 这里要更新积分领取记录
                // 查询上级
                $Superioruser = Db::table('tg_user')->field('*')->where(['tgid' => $user['SuperiorID']])->find();
                if ($Superioruser) {
                    // 获取上级信息
                    // 上级的TGID(接受者)
                    $source = $Superioruser['tgid'];
                    // 上级的上级
                    $SuperiorID = $Superioruser['SuperiorID'];
                    // 本人的tgid(贡献者)
                    $usertg = $user['tgid'];
                    $integral = $integral / 2;
                    $i = 0;
                    while ($Superioruser && $i < 6) {
                        Db::table('tg_user')->where(['tgid' => $source])->update(['Teamrewards' => $Superioruser['Teamrewards'] + $integral]);
                        // 这里要记录 积分类型(团队积分还是个人积分)   数量   方法(增还是减少)  来源  备注
                        // 来源是当前的ID
                        Db::table('tg_Record')->insert(['user' => $source, 'type' => 'team', 'amount' =>  $integral, 'source' => $usertg, 'method' => 'add']);
                        // 查询下一个(tgid)
                        $usertg = $Superioruser['tgid'];
                        $Superioruser = Db::table('tg_user')->field('*')->where(['tgid' => $SuperiorID])->find();
                        $source = $Superioruser['tgid'];
                        // 上级的上级
                        $SuperiorID = $Superioruser['SuperiorID'];
                        // 本人的tgid(贡献者)
                        $integral = $integral / 2;
                        $i++;
                    }
                }
                echo json_encode(retur('成功', ['arr' => $Superioruser, 'integral' => $newintegral, 'Collectiontime' => $Collectiontime]));
            }
        } catch (\Throwable $th) {
            //throw $th;
            echo json_encode(retur('失败', '非法访问', 422));
        }
    }

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
}
// AKIARZBOBYRZJQW2C2V7
// 1nH8XHeKl15V4A9eSDRwd41mQzMUh9xQd/1nTMbg