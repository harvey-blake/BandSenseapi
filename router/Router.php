<?php
// 路由类

namespace router;

use function common\dump;

class Router
{
    public static function parse(): array
    {
        // 从URL中,解析出:控制器, 方法, 参数

        // 默认控制器,方法和参数
        // 如果都不存在  name就是默认方法 和默认控制器
        //
        $VERSION = '';
        $controller = CONFIG['app']['default_controller'];
        $action = CONFIG['app']['default_action'];
        $params = [];

        // 判断是否存在 pathinfo
        // 这里设置路径
        // 获取版本 路径 类名
        // 最少需要三个参数
        // /v1/admin/index
        // 版本/控制器/方法

        if (array_key_exists('PATH_INFO', $_SERVER) && $_SERVER['PATH_INFO'] !== '/') {
            // dump($_SERVER['PATH_INFO']);
            // dump(array_filter(explode('/', $_SERVER['PATH_INFO'])));
            $pathinfo = array_filter(explode('/', $_SERVER['PATH_INFO']));
            // dump($pathinfo);
            // 必须满足 版本控制器 方法  默认的直接去失败的

            if (count($pathinfo) >= 4) {
                // dump(count($pathinfo));
                $APP_LICATION = array_shift($pathinfo);
                $VERSION = array_shift($pathinfo);
                $controller = array_shift($pathinfo);
                $action = array_shift($pathinfo);
                $params = $pathinfo;
            } else if (count($pathinfo) >= 3) {
                $APP_LICATION = array_shift($pathinfo);
                $controller = array_shift($pathinfo);
                $action = array_shift($pathinfo);
                $params = $pathinfo;
            } else {
                $APP_LICATION = array_shift($pathinfo);
            }
        }

        // 打印
        // dump([$APP_LICATION, $VERSION, $controller, $action, $params]);
        return [$APP_LICATION, $VERSION, $controller, $action, $params];
    }
}
