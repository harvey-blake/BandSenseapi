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

// 写入
class CreateController
{

    public function index()
    {
        dump('成功');
    }
}