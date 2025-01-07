<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <table border="1">
        <caption>用户信息表</caption>
        <thead>
            <tr>
                <th>id</th>
                <th>姓名</th>
                <th>性别</th>
                <th>邮箱</th>
            </tr>
        </thead>
        <tbody>
            <?= count($users) ?>
            <?php foreach ($users as $user) : ?>
            <tr>
                <td><?=$user['id']?>
                </td>
                <td><?=$user['uname']?>
                </td>
                <td><?=$user['sex']?>
                </td>
                <td><?=$user['email']?>
                </td>
            </tr>

            <?php endforeach ?>
        </tbody>
    </table>
</body>

</html>
