<?php
// 入口

// 2. 加载MVC框架的核心类库
if (strtolower($_SERVER['REQUEST_METHOD']) == 'options') {
    exit;
}
require  dirname(__DIR__) . '/core/App.php';

use core\App;

// 3. 启动框架
App::run();
