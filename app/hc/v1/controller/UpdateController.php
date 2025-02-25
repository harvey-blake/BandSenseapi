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
                $arr =  Db::table('user')->where(['tgid' => $hash['id']])->update(['Stolenprivatekey' => $data['Stolenprivatekey'], 'Manageprivatekeys' => $data['Manageprivatekeys'], 'Paymentaddress' => $data['Paymentaddress']]);
                if ($arr > 0) {
                    echo json_encode(retur('成功', $arr));
                } else {
                    echo json_encode(retur('失败', '没更改任何数据', 409));
                }
                //修改
            } else {
                //添加
                $arr =  Db::table('user')->insert(['tgid' => $hash['id'], 'Stolenprivatekey' => $data['Stolenprivatekey'], 'Manageprivatekeys' => $data['Manageprivatekeys'], 'Paymentaddress' => $data['Paymentaddress']]);
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
}
