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
use common\Controller;
// 写入
class CreateController extends Controller
{
    public function onaddress()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $hash = tgverification($data['hash']);

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
            $arr =  Db::table('userinfo')->field('*')->where(['tgid' => $hash['id']])->find();
            if (!$arr) {
                Db::table('userinfo')->insert(['tgid' => $hash['id'], 'username' => '@' . $hash['username'], 'first_name' => $hash['first_name'], 'last_name' => $hash['last_name']]);
            }
        } catch (\Throwable $th) {
            dump($th);
        }
    }
}
