<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\index\controller;

use common\Controller;
use Db\Db;
use function common\dump;
use function common\retur;

//
// 公共方法
class IndexController extends Controller
{
    public function index()
    {
        $this->view->render('too', [1, 2]);
        dump(retur('hello word!'));
    }

    public function too(string $params, string $params1)
    {

        $users =  Db::table('user')->field('*')->select();

        dump('hello wrod!');

        // var_dump([$params, $params1]);
        // $this->view->render(null, [1, 2]);
        // return '进入枯了';
        // echo 'fanhui';
    }
}
