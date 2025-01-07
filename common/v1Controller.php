<?php

// 控制器类的基类 相当于所有控制器的祖先

namespace common;

use view\View;
// use app\edu\v1\controller\ToquillController;

class v1Controller extends Controller
{
    // 视图对象

    // 自己写的模块
    protected View $view;
    protected  $model;
    protected  $Toquill;

    // 构造器
    public function __construct(View $view, $model, $Toquill)
    {

        // 应该在这里面NEW 数据库
        $this->view = $view;
        $this->model = $model;
        $this->Toquill = $Toquill;
    }
}
