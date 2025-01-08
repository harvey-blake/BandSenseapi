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
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', '未知原因', 400));
        }
    }
}
