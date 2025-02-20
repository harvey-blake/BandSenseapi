<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\hc\v1\controller;


use Db\Db;
use function common\dump;
use function common\retur;


use common\Controller;

class  UpdateController extends Controller
{

    function tgverification($data)
    {

        // 解析接收到的 URL 编码的数据

        $botToken = '7949382682:AAGhPeyqz4ru183scmko8bIjdxp37G3Bs0k'; // 替换为你的 Bot Token

        // 解码接收到的URL编码数据
        $decodedString = urldecode($data);

        // 将解码后的数据转换为数组
        parse_str($decodedString, $params);

        // 提取并移除 'hash' 参数
        $receivedHash = $params['hash'];
        unset($params['hash']);

        // 按字母顺序对剩余的参数进行排序
        ksort($params);

        // 生成数据检查字符串，使用换行符分隔
        $dataCheckString = '';
        foreach ($params as $key => $value) {
            $dataCheckString .= "$key=$value\n";
        }
        $dataCheckString = rtrim($dataCheckString); // 移除最后一个换行符

        // 生成 secretKey：将 botToken 作为密钥生成 HMAC 的 secretKey
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);

        // 生成 HMAC-SHA256 的哈希值
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        // 比较哈希值，判断数据是否有效
        if (hash_equals($calculatedHash, $receivedHash)) {
            $params = json_decode($params['user'], true);
            return $params;
        } else {
            return false;
        }
    }


    public function user()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $hash =  self::tgverification($data['hash']);

            if (!$hash) {
                echo json_encode(retur('失败', '非法访问', 409));
                exit;
            }

            $arr =  Db::table('user')->field('*')->where(['tgid' => $hash['id']])->find();
            if ($arr) {
                $arr =  Db::table('user')->where(['tgid' => $hash['id']])->update(['Stolenprivatekey' => $data['Stolenprivatekey'], 'Manageprivatekeys' => $data['Manageprivatekeys'], 'Paymentaddress' => $data['Paymentaddress']]);
                if ($arr > 0) {
                    echo json_encode(retur('成功', $arr));
                } else {
                    echo json_encode(retur('失败', '没更改任何数据', 409));
                }
                //修改
            } else {
                //添加
                $arr =  Db::table('user')->insert(['tgid' => $hash['id'], 'Stolenprivatekey' => $data['Stolenprivatekey'], 'Manageprivatekeys' => $data['Manageprivatekeys'], 'Paymentaddress' => $data['Paymentaddress']]);
                if ($arr > 0) {
                    echo json_encode(retur('成功', $arr));
                } else {
                    echo json_encode(retur('失败', '添加失败请查看参数', 422));
                }
            }
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', '非法访问', 500));
        }
    }
}
