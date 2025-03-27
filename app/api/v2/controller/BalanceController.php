<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\api\v2\controller;


use Db\Db;
use function common\dump;
use function common\retur;
use common\Controller;
use Web3\Web3;
use Web3\Contract;
use common\CallbackController;
use Web3\Providers\HttpAsyncProvider;

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

    public function upBalance()
    {
        // 解析前端传来的 JSON 数据
        $data = json_decode(file_get_contents('php://input'), true);

        // 验证用户身份，获取用户信息（如 id、钱包地址、余额等）
        $user = self::isvalidateJWT();

        // 创建回调对象，用于 Web3 异步调用
        $myCallback = new CallbackController();

        // 连接 Polygon (Matic) 主网的 RPC 节点
        $web3 = new Web3('https://polygon-bor-rpc.publicnode.com');

        // 定义 ERC-20 代币的 ABI，仅包含 balanceOf 方法
        $abi = json_decode('[{"constant":true,"inputs":[{"name":"_owner","type":"address"}],"name":"balanceOf","outputs":[{"name":"balance","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"}]');

        // 创建合约实例
        $contract = new Contract($web3->provider, $abi);

        // 调用 balanceOf 查询用户的代币余额
        $contract->at('0x8f3Cf7ad23Cd3CaDbD9735AFf958023239c6A063')->call('balanceOf', $user['address'], $myCallback);

        // 获取 balanceOf 方法返回的余额（单位为 wei）
        $balance =  $myCallback->result['balance']->value;

        // 将余额转换为正常显示的格式（从 wei 转换为 ETH 单位，18 位小数）
        $balance = bcdiv($balance, 10 ** 18, 18);

        // 计算用户新查询的余额和数据库中余额之间的差值
        $amount = bcsub($balance, $user['originalamount'], 18);

        // 只有当余额增加时才更新数据库，避免重复操作
        if ($amount > 0) {
            // 计算用户充值后的新余额
            $Rechargeamount = bcadd($user['Balance'], $amount, 18);

            // 更新用户的数据库余额，并记录最新的原始区块链余额
            Db::table('user')->where(['id' => $user['id']])->update(['Balance' => $Rechargeamount, 'originalamount' => $balance]);
            //记录充值
            Db::table('Balancerecord')->insert(['userid' => $user['id'], 'Amount' => $amount, 'Remark' => '用户充值']);
            // 返回 JSON 响应，通知前端充值成功，并返回最新余额
            echo json_encode(retur('成功', $Rechargeamount));
        } else {

            Db::table('user')->where(['id' => $user['id']])->update(['originalamount' => $balance]);
            echo json_encode(retur('成功', 0));
        }
    }
}
