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
class CreateController extends Controller
{
    public function onaddress()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $hash = tgverification($data['hash']);
            if (!$hash) {
                echo json_encode(retur('失败', '非法访问', 409));
                exit;
            }

            $Permissions =  Db::table('userinfo')->field('*')->where(['tgid' => $hash['id'], 'monitor' => 1])->find();
            if (!$Permissions) {
                echo json_encode(retur('失败', '没有权限,请开通后使用', 403));
                exit;
            }

            $arr =  Db::table('tokenlist')->field('*')->where(['chain' => $data['chain'], 'address' => $data['token']])->find();

            if ($arr) {
                $istoken =   Db::table('onaddress')->field('*')->where(['chain' => $data['chain'], 'address' => $data['address'], 'userid' => $hash['id'], 'tokenname' => $arr['name'], 'token' => $data['token']])->find();
                if ($istoken) {
                    echo json_encode(retur('失败', '相同监听已存在', 409));
                    exit;
                }
                $arr =  Db::table('onaddress')->insert(['chain' => $data['chain'], 'address' => $data['address'], 'userid' => $hash['id'], 'tokenname' => $arr['name'], 'token' => $data['token']]);
                if ($arr > 0) {
                    echo json_encode(retur('成功', $arr));
                } else {
                    echo json_encode(retur('失败', '添加失败请查看参数', 422));
                }
            } else {
                echo json_encode(retur('失败', '代币不存在', 409));
            }
        } catch (\Throwable $th) {
            dump($th);
        }
    }
    public function reg()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $hash = tgverification($data['hash']);
            Db::table('msg')->insert(['json' => $data]);
            $arr =  Db::table('userinfo')->field('*')->where(['tgid' => $hash['id']])->find();
            if (!$arr) {

                $decodedString = urldecode($data['hash']);
                $params = [];
                parse_str($decodedString, $params);
                $startParam = isset($params['start_param']) ? $params['start_param'] : null;
                $insert = [];
                $startinfo = false;
                if ($startParam) {
                    $startinfo =  Db::table('userinfo')->field('*')->where(['tgid' => $startParam])->find();
                }
                $mnemonic = mnemonic();
                if ($startParam && $startParam != $hash['id'] && $startinfo) {
                    $insert = ['tgid' => $hash['id'], 'username' => '@' . $hash['username'], 'first_name' => $hash['first_name'], 'last_name' => $hash['last_name'], 'address' => $mnemonic['address'], 'privateKey' => $mnemonic['privateKey'], 'Superior' => $startParam];
                } else {
                    $insert = ['tgid' => $hash['id'], 'username' => '@' . $hash['username'], 'first_name' => $hash['first_name'], 'last_name' => $hash['last_name'], 'address' => $mnemonic['address'], 'privateKey' => $mnemonic['privateKey']];
                }


                Db::table('userinfo')->insert($insert);
            } elseif (!$arr['address'] || !$arr['privateKey']) {
                $mnemonic = mnemonic();
                Db::table('userinfo')->where(['tgid' => $hash['id']])->update(['address' => $mnemonic['address'], 'privateKey' => $mnemonic['privateKey']]);
            }
            // 每次登陆 都要去查询一下余额
            //并检测用户是否充值

            if ($arr['address'] && $arr['privateKey']) {

                //正常这里是要循环多次  可以支持多个链 多个币

                $myCallback = new CallbackController();
                $web3 = new Web3('https://polygon-bor-rpc.publicnode.com');
                $abi = json_decode('[{"constant":true,"inputs":[{"name":"_owner","type":"address"}],"name":"balanceOf","outputs":[{"name":"balance","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"}]');
                $contract = new Contract($web3->provider, $abi);
                // 查询余额
                $contract->at('0x8f3Cf7ad23Cd3CaDbD9735AFf958023239c6A063')->call('balanceOf', $arr['address'], $myCallback);
                // 处理结果(可能每个代币都不一样，到时候需要修改的)
                $balance =  $myCallback->result['balance']->value;
                $balance = bcdiv($balance, 10 ** 18, 18);
                //计算充值金额
                $amount =  bcsub($balance, $arr['Balance'], 18);
                if ($amount > 0) {
                    //充值
                    $Rechargeamount = bcadd($arr['Balance'], $amount, 18);
                    Db::table('userinfo')->where(['tgid' => $hash['id']])->update(['Balance' => $Rechargeamount, 'originalamount' => $balance]);
                    //记录充值地址
                    echo json_encode(retur('成功', $Rechargeamount));
                } else {
                    Db::table('userinfo')->where(['tgid' => $hash['id']])->update(['originalamount' => $balance]);
                    echo json_encode(retur('成功', 0));
                }
            }
        } catch (\Throwable $th) {
            dump($th);
        }
    }
    public function buy()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $hash = tgverification($data['hash']);
        if (!$hash) {
            echo json_encode(retur('失败', '非法访问', 409));
            exit;
        }

        $ordr = ['monitor' => 200, 'transfer' => 5000];

        if (isset($ordr[$data['type']])) {
            //判断是否存在

            $user =  Db::table('userinfo')->field('*')->where(['tgid' => $hash['id'], 'Balance >=' => $ordr[$data['type']]])->find();
            if (!$user) {
                echo json_encode(retur('失败', '余额不足', 409));
                exit;
            }
            //修改用户权限 和余额
            $arr =  Db::table('userinfo')->where(['tgid' => $hash['id']])->update(['Balance' => bcsub($user['Balance'], $ordr[$data['type']], 18), $data['type'] => '1']);
            if ($arr > 0) {
                $orderNumber =  'ORD-' . date('YmdHis') . '-' . rand(1000, 9999);
                //添加订单信息
                $insert = ['tgid' => $hash['id'], 'type' => $data['type'], 'amount' => $ordr[$data['type']], 'orderNumber' => $orderNumber];
                Db::table('userorder')->insert($insert);
                //添加支付给代理的信息
                if ($user['Superior']) {
                    //获取上级xinxi
                    $Superior =  Db::table('userinfo')->field('*')->where(['tgid' => $user['Superior']])->find();
                    if ($Superior['Collection']) {
                        //计算上级收益
                        $income =   $ordr[$data['type']] * $Superior['Shareratio'];
                        //记录收益
                        $insert = ['income' => $income, 'orderNumber' => $orderNumber, 'tgid' => $Superior['tgid'], 'address' => $Superior['address']];
                        Db::table('income')->insert($insert);
                    }
                }

                echo json_encode(retur('成功', $arr));
            } else {
                echo json_encode(retur('失败', '购买失败', 409));
            }
        }

        //首先判断购买类型 对应的价格
        //然后判断用户余额
        //修改用户权限 和余额
        //添加订单信息
        //添加支付给代理的信息


    }


    public function ceshi()
    {
        try {
            $myCallback = new CallbackController();
            $web3 = new Web3(new HttpAsyncProvider('https://polygon-mainnet.g.alchemy.com/v2/s6pD2K_z7MFUSCP2xLkUEizCYM4_z3Hb'));

            $web3->clientVersion(function ($err, $version) {
                if ($err !== null) {
                    // do something
                    return;
                }
                if (isset($version)) {
                    echo 'Client version: ' . $version;
                }
            });
            $abi = json_decode('[{"constant":true,"inputs":[],"name":"decimals","outputs":[{"name":"","type":"uint8"}],"payable":false,"stateMutability":"view","type":"function"}]');
            $abi = json_decode('[{"constant":true,"inputs":[],"name":"totalSupply","outputs":[{"name":"","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"}]');
            // $abi = json_decode('[{"constant":true,"inputs":[],"name":"name","outputs":[{"name":"","type":"string"}],"payable":false,"stateMutability":"view","type":"function"}]');
            $abi = Db::table('abi')->field('*')->where(['name' => 'erc20'])->find();
            $abi = json_decode('[{"constant":true,"inputs":[{"name":"_owner","type":"address"}],"name":"balanceOf","outputs":[{"name":"balance","type":"uint256"}],"payable":false,"stateMutability":"view","type":"function"}]');


            $contract = new Contract('https://polygon-bor-rpc.publicnode.com', $abi);

            $contract->at('0x8f3Cf7ad23Cd3CaDbD9735AFf958023239c6A063')->call('balanceOf', '0x6a7f9a2592f4a942c44712f829e5018e6d668a3d', $myCallback);

            // 处理结果(可能每个代币都不一样，到时候需要修改的)

            // dump('结果', $myCallback);
            $balance =  $myCallback->result['balance']->value;
            dump($balance);


            $result = bcdiv($balance, 10 ** 18, 18);
            $amount = rtrim(rtrim($result, '0'), '.');
            dump($amount);
        } catch (\Throwable $th) {
            dump($th);
        }
    }
}
