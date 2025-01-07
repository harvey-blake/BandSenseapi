<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\jz\v1\controller;

use common\jzController;
use Db\Db;
use Web3\Web3;
use Web3\Contract;
use Web3\Contracts\Ethabi;
use Web3\Contracts\Types\Address;
use Web3\Contracts\Types\Boolean;
use Web3\Contracts\Types\Bytes;
use Web3\Contracts\Types\DynamicBytes;
use Web3\Contracts\Types\Integer;
use Web3\Contracts\Types\Str;
use Web3\Contracts\Types\Uinteger;
use Web3\Utils;
use function common\dump;
use function common\retur;
use common\CallbackController;

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
    public function components()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $arr =  Db::table('JZ_components')->where($data['condition'])->order('id', $data['offset'])->limit($data['perPage'])->page($data['page'])->select();
        foreach ($arr as &$item) {
            // 检查是否存在 'parameterlist' 字段
            if (isset($item['parameterlist'])) {
                // 解码 'parameterlist' 字段的值为 PHP 数组
                $item['parameterlist'] = json_decode($item['parameterlist'], true);
            }
            if (isset($item['page'])) {
                // 解码 'parameterlist' 字段的值为 PHP 数组
                $item['page'] = json_decode($item['page'], true);
            }
        }
        $count =  Db::table('JZ_components')->where($data['condition'])->count();
        if (count($arr) > 0) {
            echo json_encode(retur($count, $arr));
        } else {
            echo json_encode(retur($count, $arr, 422));
        }
    }
    // 仅限后台管理调用
    public function ads()
    {
        self::Crosssitever();
        $data = json_decode(file_get_contents('php://input'), true);
        $arr =  Db::table('JZ_ads')->where($data['condition'])->order('id', $data['offset'])->limit($data['perPage'])->page($data['page'])->select();
        $count =  Db::table('JZ_ads')->where($data['condition'])->count();
        if (count($arr) > 0) {
            echo json_encode(retur($count, $arr));
        } else {
            echo json_encode(retur($count, $arr, 422));
        }
    }
    public function adslist()
    {
        $arr =  Db::table('JZ_ads')->select();

        if (count($arr) > 0) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', $arr, 422));
        }
    }

    public function album()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $user = self::testandverify();
        if ($user) {
            $arr =  Db::table('JZ_album')->where(['userid' => $user['id']])->order('id', $data['offset'])->limit($data['perPage'])->page($data['page'])->select();
            foreach ($arr as &$item) {
                // 检查是否存在 'parameterlist' 字段
                if (isset($item['meta'])) {
                    // 解码 'parameterlist' 字段的值为 PHP 数组
                    $item['meta'] = json_decode($item['meta'], true);
                }
            }
            $count =  Db::table('JZ_album')->where(['userid' => $user['id']])->count();
            $Total =  Db::table('JZ_album')->where(['userid' => $user['id']])->select();
            $total = '0';
            foreach ($Total as  $value) {
                $total =   bcadd($total, $value['size'], 0);
            }
            $result = ['data' => $arr, 'count' => $count, 'total' => $total];
            echo json_encode(retur('成功', $result));
        } else {
            echo json_encode(retur('失败', '用户未登录', 422));
        }
    }

    public function Installation()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $arr =  Db::table('JZ_user')->where(['domain' => $data])->select();
        if (count($arr) > 0) {
            echo json_encode(retur('成功', true));
        } else {
            echo json_encode(retur('失败', false, 422));
        }
    }
    // 获取用户网站配置
    public function Websiteparameters()
    {

        // $data = json_decode(file_get_contents('php://input'), true);
        self::testandverify();
        // 查询这个用户的网站配置
        $arr =  Db::table('JZ_components')->field('*')->select();

        foreach ($arr as &$item) {
            // 检查是否存在 'parameterlist' 字段
            if (isset($item['parameterlist'])) {
                // 解码 'parameterlist' 字段的值为 PHP 数组
                $item['parameterlist'] = json_decode($item['parameterlist'], true);
            }
            if (isset($item['page'])) {
                $item['page'] = json_decode($item['page'], true);
            }
        }

        if (count($arr) > 0) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', false, 422));
        }
    }
    // 需要知道文件大小 存入数据库  如果空间大了 就不让存了   让用户升级
    public function Coursewareuploadvoucher()
    {
        // 要验证当前用户是否管理员
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $user = self::testandverify();
            // 判断用户空间是否足够
            $Total =  Db::table('JZ_album')->where(['userid' => $user['id']])->select();
            $total = $data['size'];
            foreach ($Total as  $value) {
                $total =   bcadd($total, $value['size'], 0);
            }
            if ($total <= 10485760) {
                $prefix = 'img_';
                $more_entropy = false; // 启用更多的熵（增加唯一性）
                $testpath = 'album/' . $user['id'] . '/';
                $espath = $testpath . uniqid($prefix, $more_entropy) . $data['suffix'];
                $arr =  parent::Fileuploadauthorization($espath);
                if ($arr) {
                    // 下面这是禁止他提示这种错误 加到编辑器设置内
                    /** "intelephense.diagnostics.undefinedProperties": false */
                    $arr->region = 'cn-beijing';
                    $arr->bucket = 'webgallery';
                    $arr->endpoint = 'https://oss-cn-beijing.aliyuncs.com';
                    $arr->path = $espath;
                    echo json_encode(retur('成功', $arr));
                } else {
                    echo json_encode(retur('失败', $arr, 422));
                }
            } else {
                echo json_encode(retur('失败', '存储空间不足', 422));
            }
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', $th, 422));
        }
        // 这里是需要验证的 验证是否登陆 验证是否管理员 或者是否课程管理员
    }

    // 查询订单

    public function  getTransactions()
    {
        $myCallback = new CallbackController();

        $enabi = new Ethabi([
            'address' => new Address,
            'bool' => new Boolean,
            'bytes' => new Bytes,
            'dynamicBytes' => new DynamicBytes,
            'int' => new Integer,
            'string' => new Str,
            'uint' => new Uinteger
        ]);
        // $contractAbi = json_decode($this->model->onfetch('*', 'dex_abi', 1, ['name' => 'recharge'])['data']['abi'], true);
        $web3 = new Web3('https://data-seed-prebsc-1-s1.binance.org:8545');
        // print_r(get_class_methods($contract));
        // 解析哈希  下面两个都是
        $web3->eth->getTransactionByHash('0xcc58bb8ae26ba26f9ef5916ae06e8190c3efb317aa7238abf5741cdcce585168', $myCallback);
        dump($myCallback->result);
        // dump(get_class_methods($web3->eth));

        // $web3->eth->getTransactionReceipt('0x954e64d21e1bf7fc3cb221851e6f09f80f213ad3d125ce16f10cf056ceee2e06', $myCallback);
        // dump($myCallback->result);
        $types = ['address', 'uint256'];
        // 解码多个参数
        $inputData = $myCallback->result->input;
        $inputParams = substr($inputData, 10);
        $decoded = $enabi->decodeParameters($types, $inputParams);
        dump($decoded);
        $value =  Utils::fromWei($decoded[1], 'ether')[0]->toString();
        dump($value);
        $web3->eth->getTransactionReceipt('0xcc58bb8ae26ba26f9ef5916ae06e8190c3efb317aa7238abf5741cdcce585168', $myCallback);
        dump($myCallback->result);

        dump($myCallback->result->logs[0]->topics[0]);
        dump($myCallback->result->logs[0]->topics[1]);
        dump($enabi->decodeParameters([$types[0]], $myCallback->result->logs[0]->topics[1])[0]);
        dump($myCallback->result->logs[0]->topics[2]);
        dump($enabi->decodeParameters([$types[0]], $myCallback->result->logs[0]->topics[2])[0]);
        dump($myCallback->result->logs[0]->data);
        dump(Utils::fromWei($enabi->decodeParameters([$types[1]], $myCallback->result->logs[0]->data)[0], 'ether')[0]->toString());
    }
    // 获取LOGO支付信息
    public function playinfo()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        self::testandverify();
        $payment =  Db::table('JZ_payment')->where(['type' => $data['type']])->find();
        if ($payment) {
            echo json_encode(retur('成功', $payment));
        } else {
            echo json_encode(retur('失败', $payment, 422));
        }
    }
    public function imgoss()
    {

        $data = json_decode(file_get_contents('php://input'), true);
        self::Crosssitever();
        try {
            $arr =  parent::Fileuploadauthorization($data['path']);
            if ($arr) {
                $arr->region = 'cn-beijing';
                $arr->bucket = 'webgallery';
                $arr->endpoint = 'https://oss-cn-beijing.aliyuncs.com';
                $arr->path = $data['path'];
                echo json_encode(retur('成功', $arr));
            } else {
                echo json_encode(retur('失败', '代币logo已存在', 422));
            }
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', $th, 422));
        }
    }
    // 上传LOGO
    public function osstokenlogo()
    {
        // 要验证当前用户是否管理员
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $user =   self::testandverify();
            if (!$data['address'] || !$data['chain']) {
                exit;
            }
            // 判断是否付费
            // 上传LOGO
            $tokenlogo =  Db::table('JZ_tokenlogo')->where(['address' => $data['address']])->find();

            // $order =  Db::table('JZ_order')->where(['domain' => $user['domain'], 'type' => 'logo', 'status' => '0'])->find();
            // if (!$order && !$tokenlogo) {
            //     echo json_encode(retur('失败', '支付', 466));
            //     exit;
            // }
            $tokenlogo = false;
            if (!$tokenlogo) {
                $espath = 'token/' . $data['chain'] . '/' . $data['address'] . '.png';;
                $arr =  parent::Fileuploadauthorization($espath);
                if ($arr) {
                    // 下面这是禁止他提示这种错误 加到编辑器设置内
                    /** "intelephense.diagnostics.undefinedProperties": false */
                    $arr->region = 'cn-beijing';
                    $arr->bucket = 'webgallery';
                    $arr->endpoint = 'https://oss-cn-beijing.aliyuncs.com';
                    $arr->path = $espath;
                    echo json_encode(retur('成功', $arr));
                } else {
                    echo json_encode(retur('失败', $arr, 422));
                }
            } else {
                echo json_encode(retur('失败', '代币logo已存在', 422));
            }
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', $th, 422));
        }
        // 这里是需要验证的 验证是否登陆 验证是否管理员 或者是否课程管理员
    }

    //查询网站配置  不需要权限
    public function parameters()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data['page'] && $data['domain']) {
            $arr =  Db::table('JZ_parameters')->field('*')->where(['domain' => $data['domain'], 'page' => $data['page']])->find();
            if (!$arr) {
                $arr =  Db::table('JZ_parameters')->field('*')->where(['domain' => 'localhost', 'page' => $data['page']])->find();
            }
            if ($arr['Configuration']) {
                $arr['Configuration'] =   json_decode($arr['Configuration']);
            }
            if ($arr) {
                echo json_encode(retur('成功', $arr));
                return;
            }
        }
        echo json_encode(retur('失败', '未查询到任何数据', 422));
    }
    // 查询导航
    public function navigation()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $user = self::testandverify();
        if ($user) {
            $arr =  Db::table('ZJ_navigation')->where(['domain' => $user['domain']])->order('sort', $data['offset'])->limit($data['perPage'])->page($data['page'])->select();
            $count =  Db::table('ZJ_navigation')->where(['domain' => $user['domain']])->count();
            foreach ($arr as &$item) {
                // 检查是否存在 'parameterlist' 字段
                if (isset($item['name'])) {
                    // 解码 'parameterlist' 字段的值为 PHP 数组
                    $item['name'] = json_decode($item['name'], true);
                }
            }
            $result = ['data' => $arr, 'count' => $count];
            echo json_encode(retur('成功', $result));
        }
    }
    // 前端查询
    public function navigations()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $arr =  Db::table('ZJ_navigation')->where(['domain' => $data['domain'], 'state' => '1'])->order('sort', $data['offset'])->limit($data['perPage'])->page($data['page'])->select();
        foreach ($arr as &$item) {
            // 检查是否存在 'parameterlist' 字段
            if (isset($item['name'])) {
                // 解码 'parameterlist' 字段的值为 PHP 数组
                $item['name'] = json_decode($item['name'], true);
            }
        }

        if (count($arr) > 0) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', false, 422));
        }
    }
    // 前端查询
    public function tokenlist()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $arr =  Db::table('ZJ_tokenlist')->where(['chain' => $data['chain']])->select();
        if (count($arr) > 0) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', false, 422));
        }
    }
    public function acting()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $arr =  Db::table('JZ_user')->field('dealerid')->where(['domain' => $data['domain']])->find();
        if ($arr) {
            $arr =  Db::table('JZ_dealer')->field('address')->where(['id' => $arr['dealerid']])->find();
            if ($arr) {
                echo json_encode(retur('成功', $arr));
            } else {
                echo json_encode(retur('失败', false, 422));
            }
        } else {
            echo json_encode(retur('失败', false, 422));
        }
    }
    public function chainlist()
    {
        $arr =  Db::table('JZ_chain')->select();

        if (count($arr) > 0) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', false, 422));
        }
    }
    public function chain()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $arr =  Db::table('JZ_chain')->where(['chain' => $data])->find();

        if ($arr) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', false, 422));
        }
    }
    public function websettings()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $arr =  Db::table('JZ_Websettings')->where(['domain' => $data['domain']])->find();
        if ($arr) {
            echo json_encode(retur('成功', $arr));
        } else {
            echo json_encode(retur('失败', false, 422));
        }
    }
}
