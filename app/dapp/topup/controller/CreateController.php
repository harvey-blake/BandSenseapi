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
namespace app\dapp\topup\controller;


use Db\Db;
use function common\dump;
use Ramsey\Uuid\Uuid;
use function common\retur;
use function common\Message;
use function common\invitationmessage;
use function common\tgverification;
use function common\generateUniqueCode;
// 写入
class CreateController
{
    // VIP验证
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

    public function binding()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        // 解析接收到的 URL 编码的数据
        if (!$data['info']['mac']) {
            http_response_code(401);
            exit;
        }
        $ver =  tgverification($data['sing']);
        //

        if (!$ver) {
            http_response_code(402);
            exit;
        }
        // 查询是否已经绑定
        $Whethertobind =  Db::table('tg_mac')->field('*')->where(['tgid' => $ver['id']])->find();
        if (!$Whethertobind) {
            // 需要生成推荐码
            $newdata = ['tgid' => $ver['id']];
            if ($data['info']['superiorid']) {
                // 查询上级推荐码是否存在
                $Superioruser =  Db::table('tg_mac')->field('*')->where(['Referralcode' => $data['info']['superiorid']])->find();
                if ($Superioruser) {
                    // 给上级增加10天
                    $time = 0;
                    if (time() < $Superioruser['endtime']) {
                        // 还没过期
                        $time = intval($Superioruser['endtime']) + 86400 * 3;
                    } else {
                        // 已经过期
                        $time = time() + 86400 * 3;
                    }
                    Db::table('tg_mac')->where(['Referralcode' => $data['info']['superiorid']])->update(['endtime' => $time]);
                    $newdata['endtime'] = time() + 86400 * 3;;
                    $newdata['SuperiorID'] = $data['info']['superiorid'];
                }
            }
            // 获取推广码
            $user =  Db::table('tg_mac')->field('Referralcode')->select();
            $result = array_map('current', $user);
            $code =  generateUniqueCode($result);
            $newdata['Referralcode'] =   $code;
            Db::table('tg_mac')->where(['userid' => $data['info']['mac']])->update($newdata);
            echo json_encode($ver);
        } else {
            http_response_code(500);
        }
    }
}
