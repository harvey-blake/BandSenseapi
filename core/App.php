<?php

// 框架应用的基础类,用于启动框架


namespace core;




use router\Router;
use PDO;
use Db\Db;
use function common\dump;


class App
{
    public static function run()
    {
        // 1. 启动会话
        session_start();
        // DIRECTORY_SEPARATOR: 目录分隔符常量, 可以自适应当前操作系统
        require  dirname(__DIR__) . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR .  'common.php';

        // 3. 设置常量
        self::setConst();
        require ROOT_PATH . '/vendor/autoload.php';
        // 同一个命名空间内  可以直接new
        spl_autoload_register([__CLASS__, 'autoloader']);
        // 5. 路由解析
        // 返回类名
        [$APP_LICATION, $VERSION, $controller, $action, $params] = Router::parse();
        define("VERSION", $VERSION);
        // 6. 实例化控制器

        // 首字母大写, 加上后缀: Controller
        $controller = ucfirst($controller) . 'Controller';
        //! 控制器命名空间拼接
        //!  https://v1.dexc.pro/项目名称/版本/控制器/方法
        // ! 不存在版本的时候  https://v1.dexc.pro/项目名称/控制器/方法
        $controller = VERSION ? 'app\\' .  $APP_LICATION . '\\'  . VERSION . '\\controller\\' . $controller : 'app\\' .  $APP_LICATION .  '\\controller\\' . $controller;
        // $controller = 'app\\' .  $APP_LICATION . '\\'  . VERSION . '\\controller\\' . $controller;
        // 控制器实例化
        // $users =  Db::table('dex_TAGS')->field('*')->select();



        $controller =  new $controller();
        // 调用控制器中的方法,并传入指定的参数,完成页面的渲染

        echo  call_user_func_array([$controller, $action], $params);
    }

    // 设置常量
    private static function setConst()
    {

        // 1. 框架核心类库的路径常量
        define('CORE_PATH', __DIR__);


        // 2.根路径/项目路径常量: C:\Users\Administrator\Desktop\fram
        define('ROOT_PATH', dirname(__DIR__));

        // 3. 所有应用的入口: app/
        define('ROOT_APP_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'app');
        // 还要获取当前的应用路径

        // 4. 配置常量
        // 数据配置
        $defaultConfig =  ROOT_PATH . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';;

        $pathinfo = array_filter(explode('/', $_SERVER['PATH_INFO']));

        // 必须满足 版本控制器 方法  默认的直接去失败的
        $appConfig = '';
        if (count($pathinfo) >= 2) {
            $APP_LICATION = array_shift($pathinfo);
            $VERSION = array_shift($pathinfo);
            $appConfig =  ROOT_APP_PATH . DIRECTORY_SEPARATOR . $APP_LICATION . DIRECTORY_SEPARATOR . $VERSION . DIRECTORY_SEPARATOR . 'config.php';
        }

        define('CONFIG', require file_exists($appConfig) ? $appConfig : $defaultConfig);
        // 5. 设置调试开关
        // php.ini
        ini_set('display_errors', CONFIG['app']['debug'] ? 'On' : 'Off');
    }

    //  自动加载器(类)
    private static function autoloader($class)
    {
        // 类文件的命名空间,应该与类文件所在的路径存在一一对应关系
        $file = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';

        // dump(CONFIG['app']['debug']);
        if (file_exists($file)) {
            require $file;
        } else if (CONFIG['app']['debug']) {
            die($class . ' 类文件找不到');
        }
        // file_exists($file) ?  require $file : if(CONFIG['app']['debug'])  ? die($class . ' 类文件找不到') : '';
    }
}
