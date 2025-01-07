<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\jz\v1\controller;

use common\jzController;
use Db\Db;
use function common\dump;
use function common\retur;

class  DeleteController extends jzController
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
        $arr =  Db::table('JZ_components')->where($data)->delete();
        if ($arr > 0) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', '没删除任何数据', 404));
        }
    }
    // 删除
    public function album()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $user = self::testandverify();
        if ($user && $data) {
            $img =  Db::table('JZ_album')->where(['id' => $data])->find();
            if ($img['userid'] == $user['id']) {
                // 可以删除
                // dump($img['path']);
                parent::deleteOSSFile($img['path']);
                $arr =  Db::table('JZ_album')->where(['id' => $data])->delete();

                echo json_encode(retur('成功', $arr));
            } else {
                // 不是它的图片  不可以删除
                echo json_encode(retur('失败', '没删除任何数据', 404));
            }
        } else {
            echo json_encode(retur('失败', '没删除任何数据', 404));
        }
    }
    // 删除需要删除图片
    public function ads()
    {
        self::Crosssitever();
        $data = json_decode(file_get_contents('php://input'), true);
        $arr =  Db::table('JZ_ads')->where(['id' => $data])->select();
        foreach ($arr as $value) {
            parent::deleteOSSFile($value['img']);
        }
        $arr =  Db::table('JZ_ads')->where(['id' => $data])->delete();
        echo json_encode(retur('成功', $arr));
    }
    // 查询导航
    public function navigation()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $user = self::testandverify();
        if ($user) {

            $arr =  Db::table('ZJ_navigation')->where(['id' => $data['id'], 'domain' => $user['domain']])->delete();
            if ($arr > 0) {
                echo json_encode(retur('成功', $arr));
            } else {
                echo json_encode(retur('失败', '没删除任何数据', 404));
            }
        } else {
            echo json_encode(retur('失败', '非法数据', 404));
        }
    }
}
