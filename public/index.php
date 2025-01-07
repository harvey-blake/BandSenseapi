<?php

// require  dirname(__DIR__) . '/view/View.php';
require  dirname(__DIR__) . '/common/common.php';
require  dirname(__DIR__) . '/common/Controller.php';
// require  dirname(__DIR__) . '/common/v1Controller.php';
require  dirname(__DIR__) . '/app/edu/v2/controller/FooController.php';

use app\edu\v2\controller\FooController;
use function common\dump;

// dump('123');

// $view =  new View('', '', '');
// dump($view);
$class = new ReflectionClass('app\edu\v2\controller\FooController');
// dump($class);

$Methods  = $class->getMethods();
// dump($Methods);
foreach ($Methods as $method) {
    // 忽略非公共方法和构造函数
    if (!($method->isPublic() && !$method->isConstructor())) {
        continue;
    }
    $apiName = $method->getName();
    // 获取所有注释
    $docComment = $method->getDocComment();
    // parseDocBlock($docComment);
    // dump($docComment);
    if ($docComment) {
        $apiDoc[$apiName] = array(
            "name" => $apiName, //名称  参数
            "params" => $docComment, //参数
            // "returnType" => $returnType,
            // "annotations" => $annotations
        );
    }
}
dump($apiDoc);