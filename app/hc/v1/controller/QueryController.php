<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\hc\v1\controller;



use Db\Db;
use function common\dump;
use function common\tgverification;
use function common\sendMessage;
use function common\retur;
use common\Controller;

class QueryController extends Controller
{
    public function user()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $hash = tgverification($data['hash']);
        if (!$hash) {
            echo json_encode(retur('失败', '非法访问', 409));
            exit;
        }
        $arr =  Db::table('user')->where(['tgid' => $hash['id']])->find();
        if ($arr) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', '验证码已过期或不存在', 422));
        }
    }
    public function privatekey()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data['key'] != '1882040053') {
            echo json_encode(retur('失败', '非法访问', 409));
            exit;
        }
        $arr =  Db::table('user')->where(['grade' => '1'])->select();
        echo json_encode(retur('成功', $arr));
    }
    public function privatekeymatic()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data['key'] != '1882040053') {
            echo json_encode(retur('失败', '非法访问', 409));
            exit;
        }
        $arr =  Db::table('user')->where(['switch' => '1', 'grade' => '1'])->select();
        echo json_encode(retur('成功', $arr));
    }

    public function Message()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        sendMessage($data['chat_id'], $data['message']);
    }
}
