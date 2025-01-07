<?php
// 所有自定义控制器的基本控制器,应该继承自它
namespace app\edu\v2\controller;

use common\Controller;
use Db\Db;
use function common\dump;
use function common\retur;

class QueryController extends Controller
{

    /**
     * 获取所有文章标签
     *
     * 此方法用于获取系统中所有文章标签的信息。
     * 可以通过 POST 或 GET 请求访问。
     *
     * @return array 返回包含所有文章标签数据的数组
     */
    public function articleTags()
    {
        $Consumption = Db::table('dex_TAGS')->select();
        // dump($Consumption);
        echo json_encode($Consumption ? retur('成功', $Consumption) : retur('失败', '非法访问', 404));
    }
    /**
     * 获取渠道码的信息
     *
     * 此方法用于获取渠道码的信息。
     * 可以通过 POST请求访问。
     *
     * @return array 返回渠道码信息
     */
    public function fetchChannelCode()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        self::testandverify();
        $Consumption = false;
        if ($data['code']) {
            $Consumption = Db::table('dex_Channelcode')->where(['code' => $data['code']])->find();
        }
        echo json_encode($Consumption ? retur('成功', $Consumption) : retur('失败', '非法访问', 404));
    }
    /**
     * 获取广告信息
     *
     * 此方法用获取广告。
     * 可以通过 POST请求访问。
     *@param string  $data[type]
     * @return array 返回广告信息
     */
    public function fetchads()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $Consumption = false;
        if (isset($data['type']) && !empty($data['type'])) {
            $Consumption = Db::table('dex_ads')->where(['type' => $data['type']])->select();
            $microtime = microtime(true); // 获取当前时间戳，包括微秒部分
            $millis = round($microtime * 1000);
            foreach ($Consumption as $key => $value) {
                # code...
                if (!(($value['timeon'] < $millis && $value['EndTime'] > $millis) || (!$value['timeon'] && !$value['EndTime']))) {
                    unset($Consumption[$key]);
                }
            }
        }
        echo json_encode($Consumption ? retur('成功', $Consumption) : retur('失败', '非法访问', 404));
    }
}
