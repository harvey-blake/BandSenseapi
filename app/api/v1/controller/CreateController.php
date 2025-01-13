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
namespace app\api\v1\controller;

use Db\Db;
use function common\dump;
use function common\retur;
use common\Controller;
// 写入
class CreateController extends Controller
{



    public function tokenlist()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $user = self::validateJWT();
        $arr =  Db::table('tokenlist')->field('*')->where(['pair' => $data['pair']])->find();
        if (!$arr) {
            $data['id'] = $user['id'];

            $arr =  Db::table('tokenlist')->insert($data);
            if ($arr > 0) {
                echo json_encode(retur('成功', $arr));
            } else {
                echo json_encode(retur('失败', '添加失败请查看参数', 422));
            }
        } else {
            echo json_encode(retur('失败', '交易对已经存在', 409));
        }
    }
    public function addStrategy()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $user = self::validateJWT();
        $apikey =  Db::table('binance_key')->field('*')->where(['userid' => $user['id'], 'id' => $data['keyid']])->find();
        if ($apikey) {
            $arr =  Db::table('Strategy')->insert(['keyid' => $data['keyid'], 'userid' => $user['id'], 'state' => 1, 'Strategy' => $data['Strategy']]);
            if ($arr > 0) {
                echo json_encode(retur('成功', $arr));
            } else {
                echo json_encode(retur('失败', '添加失败请查看参数', 422));
            }
        } else {
            echo json_encode(retur('失败', '非法访问', 2015));
        }
    }
}
