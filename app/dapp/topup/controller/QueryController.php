<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\dapp\topup\controller;



use Db\Db;
use function common\tgverification;
use function common\dump;
use function common\mute_member;
use function common\send_unmute_button;
use function common\unmute_member;
use function common\edit_message;


class QueryController
{

    public function vipend()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $arr =  Db::table('tg_mac')->field('endtime')->where(['userid' => $data['userid']])->find();
        if ($arr) {
            echo json_encode($arr);
        } else {
            http_response_code(400);
        }
    }
    public function index()
    {
        dump('开始');
    }
    public function my()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $ver =  tgverification($data);
        // 比较哈希值，判断数据是否有效
        if ($ver) {
            $arr =  Db::table('tg_mac')->field('*')->where(['tgid' => $ver['id']])->find();
            echo json_encode($arr);
        } else {
            http_response_code(400);
        }
    }

    public function messages()
    {


        try {
            $token = '7643239681:AAGMO59IIDDzMqZ5SLi2mFnFDTi0bXLrMPY'; // 替换为你的 Bot Token
            // $api_url = "https://api.telegram.org/bot$token/getUpdates";

            // 获取 Telegram 更新
            $update = json_decode(file_get_contents('php://input'), true);

            // 检查是否有新成员加入
            //    这里有问题
            if (isset($update['message']['new_chat_members'])) {
                $chat_id = $update['message']['chat']['id'];
                $new_members = $update['message']['new_chat_members'];

                foreach ($new_members as $member) {
                    $user_id = $member['id'];
                    $first_name = $member['first_name'];

                    // 禁言新成员
                    mute_member($chat_id, $user_id, $token);

                    // 发送解除禁言按钮
                    Db::table('tg_text')->insert(['json' => $update, 'text' => $chat_id . '=' . $user_id . '=' . $first_name . '=' . $token]);

                    send_unmute_button($chat_id, $user_id, $first_name, $token);
                }
            }

            // 处理按钮点击事件
            if (isset($update['callback_query'])) {
                $callback_data = $update['callback_query']['data'];
                $chat_id = $update['callback_query']['message']['chat']['id'];
                $message_id = $update['callback_query']['message']['message_id'];
                $user_id = explode('_', $callback_data)[1];

                // 解除禁言
                unmute_member($chat_id, $user_id, $token);

                // 更新消息内容，告知用户已解除禁言
                edit_message($chat_id, $message_id, "mix蜜茶区块链游戏社区 致力于打造热门TG区块链小游戏脚本、大型区块链游戏脚本
解放双手，工具初期版本正式发布！", $token);
            }
        } catch (\Throwable $th) {
            //throw $th;
        }
    }


    public function post()
    {

        echo json_encode($_SERVER);
    }
}
