<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\api\v2\controller;


use Db\Db;
use function common\dump;
use function common\retur;
use common\Controller;

class  BalanceController extends Controller
{
    public function Withdraw()
    {

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
            echo json_encode(retur('失败', '非法访问', 422));
        }
        $user =   self::isvalidateJWT();
        //查询用户的金额
        //提现的金额  必须小于=现有余额
        //
        $result =   bcsub($user['commission'], $data['amount'], 8);

        if (bccomp($result, '0', 8) < 0) {
            echo json_encode(retur('失败', '余额不足', 422));
        }

        //减少用户的佣金
        Db::table('user')->where(['email' => $user['email']])->update(['commission' => $result]);
        //添加佣金变动记录
        Db::table('Commissionrecords')->insert(['userid' => $user['id'], 'Amount' => -floatval($data['amount']), 'Remark' => '用户提现']);
        //添加提现申请
        Db::table('Withdraw')->insert(['userid' => $user['id'], 'Amount' => $data['amount'], 'address' => $data['address'], 'Remark' => '用户提现']);
        echo json_encode(retur('成功', '申请成功,请等待审核'));
    }
}
