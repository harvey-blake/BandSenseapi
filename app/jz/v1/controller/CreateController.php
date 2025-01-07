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
namespace app\jz\v1\controller;

use common\jzController;
use Db\Db;
use function common\dump;
use function common\retur;
use Web3\Web3;
use Web3\Contract;
use Web3\Contracts\Ethabi;
use Web3\Contracts\Types\Address;
use Web3\Contracts\Types\Boolean;
use Web3\Contracts\Types\Bytes;
use Web3\Contracts\Types\DynamicBytes;
use Web3\Contracts\Types\Integer;
use Web3\Contracts\Types\Str;
use Web3\Contracts\Types\Uinteger;
use Web3\Utils;
use common\CallbackController;
// 写入
class CreateController extends jzController
{

    /**
     * 获取所有文章标签
     *
     * 此方法用于获取系统中所有文章标签的信息。
     * 可以通过 POST 或 GET 请求访问。
     *
     * @return array 返回包含所有文章标签数据的数组
     */
    public function components()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        // 管理员验证
        self::Crosssitever();
        // dump($ab);
        // return
        $arr =  Db::table('JZ_components')->insert(['name' => $data['name'], 'type' => $data['type'], 'Classification' => $data['Classification'], 'parameterlist' => $data['parameterlist'], 'Configuration' => $data['Configuration']]);
        if ($arr > 0) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', $arr, 422));
        }
    }
    public function install()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        //需要表名

        $arr =  Db::table('JZ_user')->field('*')->where(['domain' => $data['domain']])->find();
        if (!$arr) {
            // 这里特么必须删除注册用户的钱包信息
            //需要删除余额
            // 验证code
            $arr =  Db::table('JZ_activationcode')->field('*')->where(['code' => $data['code']])->find();
            if ($arr && $arr['state'] == '0') {
                Db::table('JZ_activationcode')->where(['code' => $data['code']])->update(['state' => '1', 'domain' => $data['domain']]);
                unset($data['balance'], $data['integral'], $data['code']);
                $data['dealerid'] = $arr['dealerid'];
                dump($data);
                $arr =  Db::table('JZ_user')->insert($data);
                if ($arr > 0) {
                    echo json_encode(retur('成功', $arr));
                } else {
                    echo json_encode(retur('失败', $arr, 422));
                }
            } else {
                echo json_encode(retur('错误', '无效激活码', 422));
            }
        } else {
            echo json_encode(retur('错误', '用户已存在', 422));
        }
    }
    public function album()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $user = self::testandverify();
        if ($user) {
            $data['userid'] = $user['id'];
            $arr =  Db::table('JZ_album')->insert($data);
            if ($arr > 0) {
                echo json_encode(retur('成功', $arr));
            } else {
                echo json_encode(retur('失败', $arr, 422));
            }
        } else {
            echo json_encode(retur('失败', '用户未登录', 422));
        }
    }
    public function ads()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            self::Crosssitever();
            $arr =  Db::table('JZ_ads')->insert($data);
            if ($arr > 0) {
                echo json_encode(retur('成功', $arr));
            } else {
                echo json_encode(retur('失败', $arr, 422));
            }
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
    public function parameters()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $user = self::testandverify();
        if ($user) {
            // 获取域名
            $arr =  Db::table('JZ_parameters')->field('*')->where(['domain' => $user['domain'], 'page' => $data['page']])->find();
            if ($arr) {
                $arr =  Db::table('JZ_parameters')->where(['domain' => $user['domain'], 'page' => $data['page']])->update(['Configuration' => $data['Configuration']]);
            } else {
                // 添加
                $data['domain'] = $user['domain'];
                $arr =  Db::table('JZ_parameters')->insert($data);
            }
            if ($arr > 0) {
                echo json_encode(retur('成功', $arr));
            } else {
                echo json_encode(retur('失败', $arr, 422));
            }
        }
    }
    // 导航
    public function navigation()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $user = self::testandverify();
        if ($user) {
            $data['domain'] = $user['domain'];
            $arr =  Db::table('ZJ_navigation')->insert($data);
            if ($arr > 0) {
                echo json_encode(retur('成功', $arr));
            } else {
                echo json_encode(retur('失败', $arr, 422));
            }
        }
    }
    public function tokenlogo()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $user = self::testandverify();
        if ($user && $data['address'] && $data['info'] && $data['chain']) {
            $arr =  Db::table('JZ_tokenlogo')->where(['address' => $data['address'], 'chain' => $data['chain']])->find();
            $order =  Db::table('JZ_order')->where(['domain' => $user['domain'], 'type' => 'logo', 'status' => '0'])->find();
            if (!$arr && $order) {
                Db::table('JZ_order')->where(['domain' => $order['domain'],  'type' => 'logo', 'hash' => $order['hash']])->update(['status' => '1']);
                $data['info']['hash'] = $order['hash'];
                $arr =  Db::table('JZ_tokenlogo')->insert(['user' => $user['domain'], 'chain' => $data['chain'], 'address' => $data['address'], 'info' => $data['info']]);
                if ($arr > 0) {
                    echo json_encode(retur('成功', $arr));
                } else {
                    echo json_encode(retur('失败', $arr, 422));
                }
            }
        }
    }
    public function websettings()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $user = self::testandverify();
        if ($user) {
            $arr =  Db::table('JZ_Websettings')->field('*')->where(['domain' => $user['domain']])->find();
            $data['domain'] = $user['domain'];
            if ($arr) {
                $arr =  Db::table('JZ_Websettings')->where(['domain' => $user['domain']])->update($data);
            } else {
                // 添加
                $arr =  Db::table('JZ_Websettings')->insert($data);
            }
            if ($arr > 0) {
                echo json_encode(retur('成功', $arr));
            } else {
                echo json_encode(retur('失败', $arr, 422));
            }
        }
    }
    // 订单验证
    public function  getTransaction()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $user =  self::testandverify();
        $status = false;
        $msg = '';
        // 需要检测下这个哈希未被使用
        $payment =  Db::table('JZ_payment')->where(['type' => $data['type']])->find();
        $order =  Db::table('JZ_payment')->where(['chain' => $payment['chain'], 'hash' => strtolower($data['hash'])])->find();
        if ($payment && !$order) {
            $chain =  Db::table('JZ_chain')->where(['chain' => $payment['chain']])->find();
            $myCallback = new CallbackController();
            $web3 = new Web3($chain['rpc']);
            $enabi = new Ethabi([
                'address' => new Address,
                'bool' => new Boolean,
                'bytes' => new Bytes,
                'dynamicBytes' => new DynamicBytes,
                'int' => new Integer,
                'string' => new Str,
                'uint' => new Uinteger
            ]);
            $types = ['address', 'uint256'];
            // 解码多个参数
            $web3->eth->getTransactionReceipt($data['hash'], $myCallback);
            $to =   $enabi->decodeParameters([$types[0]], $myCallback->result->logs[0]->topics[2])[0];

            $value =  Utils::fromWei($enabi->decodeParameters([$types[1]], $myCallback->result->logs[0]->data)[0], 'ether')[0]->toString();

            if (strtolower($payment['recipientaddress']) == strtolower($to) && $value >= $payment['amount']) {
                // 成功
                $arr =  Db::table('JZ_order')->insert(['domain' => $user['domain'], 'chain' => $payment['chain'], 'hash' => strtolower($data['hash']), 'type' => $data['type'], 'status' => '0']);
                if ($arr > 0) {
                    $status = true;
                    $msg .= '支付成功';
                } else {
                    $msg .= '检测失败';
                }
            } else {
                $msg .= 'hash错误或支付金额不足,请联系客服' . $payment['recipientaddress'] . '=' . $to . '=' . $value . '=' . $payment['amount'];
            }
        } else {
            $msg .= '非法提交,请联系客服';
        }
        if ($status) {
            echo json_encode(retur('成功', $msg));
        } else {
            echo json_encode(retur('失败', $msg, 422));
        }
    }
    public function permit()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $arr =  Db::table('JZ_permit')->insert($data);
        if ($arr > 0) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', $arr, 422));
        }
    }
}
