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
namespace app\api\v2\controller;

require dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'mail' . DIRECTORY_SEPARATOR . 'mail.php';

use Db\Db;
use function common\dump;
use function common\retur;
use function bandsenmail\mail;
use common\Controller;
// 写入
class CreateController extends Controller
{





    public function register()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        //判断 邮箱  密码  是否存在
        if (!isset($data['email']) || !isset($data['password'])) {
            echo json_encode(retur('失败', '参数错误', 422));
            exit;
        }
        //判断验证码是否存在
        if (!isset($data['code'])) {
            //发送验证码
            mail($data['email'], '波段智投-用户注册', '注册新账户');
            exit;
        }
        //判断验证码是否正确
        $arr =  Db::table('mailcode')->where(['mail' => $data['email']])->order('id',  'desc')->limit(1)->find();

        if ($arr['code'] != $data['code']) {
            echo json_encode(retur('失败', '验证码错误', 422));
            exit;
        }
    }

    public function ceshi()
    {
        $ip = '94.177.131.202';
        $mail = 'hbniubi@gmail.com';
        $time = date('Y-m-d H:i:s', strtotime('-24 hours'));

        // $jieguo =  Db::table('mailcode')->field('*')->where(['mail' =>  $mail, 'time >=' => $time])->count();
        // dump($jieguo);
        $arr =  Db::table('mailcode')->where(['mail' => $mail])->order('id',  'DESC')->limit('1')->select();
        dump($arr);
    }
}
