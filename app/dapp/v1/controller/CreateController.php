<?php
// 所有自定义控制器的基本控制器,应该继承自它
// 添加（插入）：

// 数据格式不正确或验证失败：422
// 客户端请求存在问题：400
// 修改（更新）：

// 资源状态的冲突：409
// 数据格式不正确或验证失败：422
// 客户端请求存在问题：400
// 删除：

// 资源未找到：404
// 客户端请求存在问题：400
// 查询：

// 资源未找到：404
// 客户端请求存在问题：400
namespace app\dapp\v1\controller;


use Db\Db;
use function common\dump;
use Ramsey\Uuid\Uuid;
use function common\retur;
use function common\Message;
use function common\invitationmessage;
// 写入
class CreateController
{
    public function tgregister()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $arr =  Db::table('tg_mac')->field('*')->where(['mac' => $data['mac']])->find();
        $userid = null;
        if (!$arr) {
            $userid = strtoupper(Uuid::uuid4());
            $arr =  Db::table('tg_mac')->insert(['mac' => $data['mac'], 'userid' => $userid, 'endtime' => time()]);
        } else {
            Db::table('tg_mac')->where(['mac' => $data['mac']])->update(['logins' =>  intval($arr['logins']) + 1]);
            $userid = $arr['userid'];
        }
        if ($userid) {
            echo json_encode(retur('成功', $userid));
        } else {
            echo json_encode(retur('失败', '未知原因', 400));
        }
    }

    public function register()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $arr =  Db::table('tg_user')->field('*')->where(['tgid' => $data['user']['id']])->find();
        if (!$arr) {
            $start = null;
            $starts = null;
            // 这里还有问题  到时候 先注册  后给下级
            if ($data['start']) {
                $starts =  Db::table('tg_user')->field('*')->where(['id' => $data['start']])->find();
                if ($starts) {
                    $start = $starts['tgid'];
                    // 提示有下级加入
                }
            }
            $arr =  Db::table('tg_user')->insert(['tgid' => $data['user']['id'], 'SuperiorID' => $start, 'first_name' => $data['user']['first_name'], 'last_name' => $data['user']['last_name'], 'username' => $data['user']['username']]);
            if ($arr > 0) {
                $integral = 200;
                if ($starts) {
                    invitationmessage($start, ($starts['first_name'] ? $starts['first_name'] : '') . ($starts['last_name'] ? $starts['last_name'] : ''));
                    Db::table('tg_user')->where(['id' => $starts['id']])->update(['Teamrewards' => $starts['Teamrewards'] + $integral]);
                    // 这里要记录 积分类型(团队积分还是个人积分)   数量   方法(增还是减少)  来源  备注
                    Db::table('tg_Record')->insert(['user' =>  $starts['tgid'], 'type' => 'team', 'amount' =>  $integral, 'source' =>  $data['user']['id'], 'method' => 'add']);
                }
                Message($data['user']['id']);
                echo json_encode(retur('成功', ['uaername' => $data['user']['uaername']]));
            } else {
                echo json_encode(retur('失败', ['uaername' => $data['user']['uaername']], 422));
            }
        } else {
            // Message($data['user']['id']);
            echo json_encode(retur('成功', '已经注册'));
        }
    }
}
