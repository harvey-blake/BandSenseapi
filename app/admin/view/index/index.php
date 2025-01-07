<!-- 默认控制器的默认模板文件 Index/index -->
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <h1>Hello <?php echo $username ?>
    </h1>
    <ul>
        <?php foreach ($items as $item): ?>
        <li><?=$item?>
        </li>
        <?php endforeach ?>
    </ul>


</body>

</html>
