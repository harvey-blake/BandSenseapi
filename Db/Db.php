<?php

// 数据库操作入口类
namespace Db;

use PDO;
use function common\dump;

class Db
{

    // 连接对象
    protected static PDO $db;

    // 连接数据库, 创建PDO对象
    protected static function connect(string $dsn, string $username, string $password)
    {

        self::$db =   new PDO($dsn, $username, $password);
    }

    public static function __callStatic(string $name, array $argc)
    {

        // 1. 连接数据库
        $dsn = CONFIG['database']['type'] . ':dbname=' . CONFIG['database']['dbname'] . '; host=' . CONFIG['database']['host'];

        $username = CONFIG['database']['username'];
        $password = CONFIG['database']['password'];

        static::connect($dsn, $username, $password);

        // 2. 将数据库所有的操作, 全部重定义到别一个类: Query.php
        $query = new Query(static::$db);
        return  call_user_func_array([$query, $name], $argc);
    }
}
