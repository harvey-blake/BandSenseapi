<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\edu\v3\controller;

use common\Controller;
use Db\Db;
use function common\dump;
//
// 公共方法
class FooController extends Controller
{

    /**
     * 获取用户信息
     * @param string $userId 用户ID
     * @param string $username 用户名
     * @return array User 用户信息
     * @throws UserNotExistsException 用户不存在异常
     */
    public function too(string $params, string $params1)
    {

        $users =  Db::table('user')->field('*')->select();

        dump('hello wrod!');

        var_dump([$params, $params1]);
        // $this->view->render(null, [1, 2]);
        // return '进入枯了';
        // echo 'fanhui';
    }
    public function approve()
    {
        dump('hello wrod!');
    }
}
// @param: 用于描述函数参数的信息。

// @param 数据类型 $变量名 参数描述
// @return: 用于描述函数返回值的信息。

// @return 数据类型 返回值描述
// @throws: 用于描述函数可能抛出的异常。

// @throws 异常类名 异常描述
// @var: 用于描述变量的信息。

// @var 数据类型 变量描述
// @deprecated: 用于标记函数或方法已被弃用。

// @deprecated 原因说明
// @see: 用于引用其他相关资源或文档。

// @see 相关资源或文档
// @inheritdoc: 用于表明当前文档块应该继承自父类或接口的文档块。

// @inheritdoc
// @example: 用于提供代码示例或用法示例。

// @example 代码示例