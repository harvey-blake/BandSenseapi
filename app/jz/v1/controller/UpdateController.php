<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\jz\v1\controller;

use common\jzController;
use Db\Db;
use function common\dump;
use function common\retur;

class  UpdateController extends jzController
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
        $condition = $data['condition'];
        unset($data['condition']);
        $arr =  Db::table('JZ_components')->where($condition)->update($data);
        if ($arr > 0) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', '没更改任何数据', 409));
        }
    }
    // 导航
    public function navigation()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $user = self::testandverify();
        if ($user['domain'] == $data['domain']) {
            $condition = ['domain' => $data['domain'], 'id' => $data['id']];
            unset($data['domain'], $data['id']);
            $arr =  Db::table('ZJ_navigation')->where($condition)->update($data);
            if ($arr > 0) {
                echo json_encode(retur('成功', $arr));
            } else {
                echo json_encode(retur('失败', '没更改任何数据', 409));
            }
        } else {
            echo json_encode(retur('失败', '非法数据', 409));
        }
    }
    public function ads()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            self::Crosssitever();
            $id = $data['id'];
            unset($data['id']);
            $arr =  Db::table('JZ_ads')->where(['id' => $id])->update($data);
            if ($arr > 0) {
                echo json_encode(retur('成功', $arr));
            } else {
                echo json_encode(retur('失败', '沒修改任何數據', 422));
            }
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}
