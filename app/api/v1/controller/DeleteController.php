<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\api\v1\controller;


use Db\Db;
use function common\dump;
use function common\retur;

class  DeleteController
{
    public function index()
    {

        $arr =  Db::table('cex_user')->field('*')->select();
        if ($arr) {
            echo json_encode(retur('成功',  $arr));
        } else {
            echo json_encode(retur('失败', $arr, 422));
        }
    }
}
