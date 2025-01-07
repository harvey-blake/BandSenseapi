<?php

namespace app\edu\v1\controller;

use function common\retur;

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use AlibabaCloud\Vod\Vod;
use Ramsey\Uuid\Uuid;
use OSS\OssClient;
use OSS\Core\OssException;
use Fukuball\Jieba\Jieba;
use Fukuball\Jieba\Finalseg;
use Web3\Web3;
use Web3\Contract;
use Web3p\EthereumTx\Transaction;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use AlibabaCloud\SDK\Sts\V20150401\Sts;
use Darabonba\OpenApi\Models\Config;
use AlibabaCloud\SDK\Sts\V20150401\Models\AssumeRoleRequest;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use function AlibabaCloud\Client\value;
use function common\ETHverifyMessage;
use common\Controller;
use common\CallbackController;
use function common\dump;


class ToquillController extends Controller
{
    protected  $model;
    protected $myCallback;
    public function __construct($model)
    {
        $this->model = $model;
        $this->myCallback = new CallbackController();
    }
    function htmlToQuillDelta($html)
    {
        $delta = array();

        $dom = new \DOMDocument();

        // 忽略无效HTML标签的错误
        libxml_use_internal_errors(true);

        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        // 清除之前的解析错误
        libxml_clear_errors();

        $body = $dom->getElementsByTagName('body')->item(0);

        if ($body) {
            foreach ($body->childNodes as $node) {
                $delta = array_merge($delta, $this->parseNode($node));
            }
        }

        return $delta;
    }

    function parseNode($node)
    {
        $delta = array();

        if ($node instanceof \DOMText) {
            // 文本节点
            $text = trim($node->nodeValue);
            if (!empty($text)) {
                $delta[] = array('insert' => $text);
            }
        } elseif ($node instanceof \DOMElement) {
            // 标签节点
            $tag = $node->tagName;

            // 处理段落标签
            if (in_array($tag, array('p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'))) {
                $delta[] = array('insert' => "\n");
                foreach ($node->childNodes as $childNode) {
                    $delta = array_merge($delta, $this->parseNode($childNode));
                }
                $delta[] = array('insert' => "\n");
            }

            // 处理加粗和斜体标签
            if (in_array($tag, array('strong', 'b', 'em', 'i'))) {
                $delta[] = array('attributes' => array('bold' => true));
                foreach ($node->childNodes as $childNode) {
                    $delta = array_merge($delta, $this->parseNode($childNode));
                }
                $delta[] = array('attributes' => array('bold' => false));
            } elseif ($tag === 'u') {
                // 处理下划线标签
                $delta[] = array('attributes' => array('underline' => true));
                foreach ($node->childNodes as $childNode) {
                    $delta = array_merge($delta, $this->parseNode($childNode));
                }
                $delta[] = array('attributes' => array('underline' => false));
            } elseif ($tag === 's' || $tag === 'strike') {
                // 处理删除线标签
                $delta[] = array('attributes' => array('strike' => true));
                foreach ($node->childNodes as $childNode) {
                    $delta = array_merge($delta, $this->parseNode($childNode));
                }
                $delta[] = array('attributes' => array('strike' => false));
            } elseif ($tag === 'sup') {
                // 处理上标标签
                $delta[] = array('attributes' => array('script' => 'super'));
                foreach ($node->childNodes as $childNode) {
                    $delta = array_merge($delta, $this->parseNode($childNode));
                }
                $delta[] = array('attributes' => array('script' => 'normal'));
            } elseif ($tag === 'sub') {
                // 处理下标标签
                $delta[] = array('attributes' => array('script' => 'sub'));
                foreach ($node->childNodes as $childNode) {
                    $delta = array_merge($delta, $this->parseNode($childNode));
                }
                $delta[] = array('attributes' => array('script' => 'normal'));
            } elseif ($tag === 'a') {
                // 处理超链接标签
                $href = $node->getAttribute('href');
                if (!empty($href)) {
                    $delta[] = array('insert' => $node->nodeValue, 'attributes' => array('link' => $href));
                }
            } elseif ($tag === 'img') {
                // 处理图片标签
                $src = $node->getAttribute('src');
                if (!empty($src)) {
                    $delta[] = array('insert' => array('image' => $src));
                }
            } elseif (in_array($tag, array('ul', 'ol'))) {
                // 处理列表标签
                $delta[] = array('insert' => "\n");
                foreach ($node->childNodes as $childNode) {
                    $delta = array_merge($delta, $this->parseNode($childNode));
                }
                $delta[] = array('insert' => "\n");
            } elseif ($tag === 'li') {
                // 处理列表项标签
                $delta[] = array('insert' => '- ');
                foreach ($node->childNodes as $childNode) {
                    $delta = array_merge($delta, $this->parseNode($childNode));
                }
                $delta[] = array('insert' => "\n");
            } elseif ($tag === 'br') {
                // 处理换行标签
                $delta[] = array('insert' => "\n");
            } elseif ($tag === 'blockquote') {
                // 处理引用块标签
                $delta[] = array('insert' => "\n");
                foreach ($node->childNodes as $childNode) {
                    $delta = array_merge($delta, $this->parseNode($childNode));
                }
                $delta[] = array('insert' => "\n", 'attributes' => array('blockquote' => true));
            } elseif ($tag === 'table') {
                // 处理表格标签
                $delta[] = array('insert' => "\n");
                foreach ($node->childNodes as $childNode) {
                    $delta = array_merge($delta, $this->parseNode($childNode));
                }
                $delta[] = array('insert' => "\n");
            } elseif (in_array($tag, array('tr', 'td', 'th'))) {
                // 处理表格行和表格单元格标签
                $delta[] = array('insert' => $node->nodeValue);
            }
            // 添加更多的HTML标签转换规则...
        } else {
            // 其他标签处理为纯文本
            $text = $node->nodeValue;
            if (!empty(trim($text))) {
                $delta[] = array('insert' => $text);
            }
        }

        return $delta;
    }
    // 处理多余的换行符
    function removeExtraNewlines($delta)
    {
        $result = array();
        $newlineCount = 0;

        foreach ($delta as $item) {
            if (isset($item['insert']) && is_string($item['insert'])) {
                // 判断是否是换行符
                $isNewline = strcmp($item['insert'], "\n") === 0;
                // $isNewline = trim($item['insert']) === "\n";

                if ($isNewline) {
                    // 如果已经有两个换行符了，就跳过多余的换行符
                    if ($newlineCount >= 2) {
                        continue;
                    }
                    $newlineCount++;
                } else {
                    $newlineCount = 0;
                }
            }

            $result[] = $item;
        }

        return $result;
    }
    // 获取文章关键词
    public function keywords($text = '')
    {
        header('Access-Control-Allow-Origin: *');
        $words = $text;
        if ($words == '') {
            $words = json_decode(file_get_contents('php://input'), true);
            $words = '小明是个王八蛋';
        }
        ini_set('memory_limit', '1024M');
        Jieba::init();
        Finalseg::init();
        $words = Jieba::cut($words);
        $stopWords = ['的', '是', '我', '你', '他', '她'];
        $filteredWords = array_diff($words, $stopWords);
        $wordFreq = array_count_values($filteredWords);
        arsort($wordFreq);
        $topKeywords = array_keys($wordFreq);
        $filteredKeywords = array_filter($topKeywords, function ($keyword) {
            return mb_strlen($keyword, 'UTF-8') > 1;
        });
        return $filteredKeywords;
    }
    // 采集
    public function getmifengcha()
    {
        // // 设置不让客户端断开连接后终止脚本
        // 采集分类
        echo 'jin';
        $type = $_GET['type'];
        // 分类id
        $id = $_GET['id'];
        // 别名
        $columnname = $_GET['columnname'];
        ignore_user_abort(true);
        set_time_limit(0);
        fastcgi_finish_request();
        // echo '断开后';
        $apiKey = 'BOTKM7KMYC6YFAJZWSE59WENSMMN0IIHUZMMF7DM';
        // 构建 API 请求 URL
        $url = 'http://data.mifengcha.com/api/v3/' . $type . '?api_key=' . $apiKey;

        // 初始化 cURL
        $ch = curl_init();
        // 设置 cURL 选项
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // 执行 cURL 请求
        $response = curl_exec($ch);
        // 关闭 cURL 资源
        curl_close($ch);
        // 解析 JSON 响应
        $data = json_decode($response, true);
        $stor = array_column($data, 'timestamp');
        array_multisort($stor, SORT_ASC, $data);
        $oldtime =  $this->model->onfetchmax('*', 'dex_article', ['pid' => $id]);
        if ($oldtime['code'] = 200) {
            $oldtime = $oldtime['data']['Creationtime'];
            $oldtime = strtotime($oldtime);
        } else {
            $oldtime = 0;
        }
        $newarr = [];
        for ($i = 0; $i < count($data); $i++) {
            // 这里把图片弄到本地
            $data[$i]['timestamp'] = $data[$i]['timestamp'] / 1000;
            $image = '';
            if ($data[$i]['timestamp'] > $oldtime) {
                for ($y = 0; $y < count($data[$i]['images']); $y++) {
                    # code...
                    $imageContent = file_get_contents($data[$i]['images'][$y]);
                    if ($imageContent !== false) {
                        $dest = 'Userimage/' . basename(parse_url($data[$i]['images'][$y], PHP_URL_PATH));
                        $result = file_put_contents($dest, $imageContent);
                        if ($result !== false) {
                            $result =  self::uploadFileToOSSAndGetURL($dest, $dest);
                            $data[$i]['content'] = str_replace($data[$i]['images'][$y], $result, $data[$i]['content']);
                            $data[$i]['images'][$y] = $result;
                            $image = $data[$i]['images'][0];
                            unlink($dest);
                        }
                    }
                }
                $data[$i]['content'] = self::htmlToQuillDelta(strip_tags($data[$i]['content'], '<hr><pre><code><img><blockquote><p><a>'));
                $data[$i]['content'] = self::removeExtraNewlines($data[$i]['content']);
                $newarr[] =    $this->model->sqladd('dex_article', ['description' => $data[$i]['description'], 'keywords' => $data[$i]['keywords'], 'thumbnail' => $image, 'userid' => 62, 'title' => $data[$i]['title'], 'content' => $data[$i]['content'], 'columnname' => $columnname, 'pid' => $id, 'Creationtime' => date('Y-m-d H:i:s', $data[$i]['timestamp']), 'source' => $data[$i]['source']]);
            }
        }
        print_r($newarr);
    }
    //树形结构
    public function treearray($data)
    {
        $tree = [];
        foreach ($data as &$value) {
            if (isset($data[$value['pid']])) {
                $data[$value['pid']]['child'][] = &$value;
            } else {
                $tree[] = &$value;
            }
        }
        return $tree;
    }

    // 文件上传
    // 单文件上传 $topath 可传入路径  如果不传入则默认路径'Userimage/'
    public function files($topath = 'Userimage/', $file = '')
    {
        // 这里获取传上来的值

        try {
            self::testandverify();
            $filename = '';
            if ($file) {
                $filename = $file;
            } else {
                $ming = key($_FILES);
                $filename = $_FILES[$ming];
            }
            $state = '5000';
            $tips = '';
            if (isset($filename)) {
                $name = $filename['name'];
                $tmpName = $filename['tmp_name'];
                $filename['error'] = $filename['size'] > 1048576 ? 3 : 0;
                $error = $filename['error'];
                if ($error > 0) {
                    switch ($error) {
                        case 1:
                            $state = '5001';
                            $tips .= '上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值';
                            break;
                        case 2:
                            $state = '5002';
                            $tips .= '文件大小超过了上传表单中MAX_FILE_SIZE最大值';
                            break;
                        case 3:
                            $state = '5003';
                            $tips .= '文件只有部分被上传';
                            break;
                        case 4:
                            $state = '5004';
                            $tips .= '没有文件被上传';
                            break;
                        case 6:
                            $state = '5005';
                            $tips .= '找不到临时目录';
                            break;
                        case 7:
                            $state = '5006';
                            $tips .= '文件写入失败,请检查目录权限';
                            break;
                    }
                } else {
                    // 判断用户是不是通过合法的POST方式上传
                    if (is_uploaded_file($tmpName)) {
                        // 设置允许上传文件类型的白名单
                        $allow = ['jpg', 'jpeg', 'png', 'gif'];
                        // 获取文件扩展名
                        $ext =  pathinfo($name)['extension'];
                        if (in_array($ext, $allow)) {
                            // 二个条件都满足了
                            // 1. post方式上传的 2. 文件类型是合法的
                            // 目标目录
                            $path = $topath;
                            // 自定义目标文件名
                            $dest = $path . md5(uniqid() . $name) . '.' . $ext;

                            // 将文件从临时目录中移动到目标目录中并重命名
                            // 这里后面要灵活设置  看用户是否有将图片存在某个网站
                            // 没有设置那么这里就等于本站
                            $dest = self::uploadFileToOSSAndGetURL($tmpName, $dest);

                            if ($dest) {
                                $state = '';
                                $tips .= '上传成功';
                            } else {
                                $state = '5010';
                                $tips .= '失败';
                            }
                        } else {
                            $state = '5011';
                            $tips .= '文件类型错误';
                        }
                    } else {
                        $state = '5000';
                        $tips = '上传方式非法';
                    }
                }
            }

            $arr = retur($tips, $dest, $state);
        } catch (\Throwable $th) {
            $arr = retur('出错了', $th, '6001');
        }
        return $arr;
    }

    public function showTree($stree, $fiele = 'name')
    {
        $resulr = [];
        foreach ($stree as  $value) {
            $child = $value['child'] ?? [];
            unset($value['child']);
            $value['cnname'] = $value[$fiele];
            $value[$fiele] = str_repeat('--', $value['level']) . $value[$fiele];
            if ($value['id']) {
                $resulr[] = $value;
            }
            if ($child) {
                $resulr = array_merge($resulr, self::showTree($child, $fiele));
            }
        }
        return $resulr;
    }
    public  function recursion($data, $pid)
    {
        static $child = [];
        foreach ($data as $key => $value) {
            if ($value['pid'] == $pid) {
                $child[] = $value;   // 满足条件的数据添加进child数组
                unset($data[$key]);  // 使用过后可以销毁
                self::recursion($data, $value['id']);   // 递归调用，查找当前数据的子级
            }
        }
        return $child;
    }
    public function recursions($data, $pid)
    {
        static $child = [];
        foreach ($data as $key => $value) {
            if ($value['pid'] == $pid) {
                $child[] = $value;   // 满足条件的数据添加进child数组
                // array_push($child, $value['id']);
                unset($data[$key]);  // 使用过后可以销毁
                self::recursions($data, $value['id']);   // 递归调用，查找当前数据的子级
            }
        }
        return $child;
    }
    public  function recursionabs($data, $pid)
    {
        static $child = [];
        foreach ($data as $key => $value) {
            if ($value['pid'] == $pid) {
                $child[] = $value['id'];   // 满足条件的数据添加进child数组
                // array_push($child, $value['id']);
                unset($data[$key]);  // 使用过后可以销毁
                self::recursionabs($data, $value['id']);   // 递归调用，查找当前数据的子级
            }
        }
        return $child;
    }
    // 查询最终上级
    function reprecursion($id, $dataMap)
    {
        while (isset($dataMap[$id]['CommentID']) && $dataMap[$id]['CommentID'] != 0) {
            $id = $dataMap[$id]['CommentID'];
        }
        return $id;
    }
    //阿里云上传
    function uploadFileToOSSAndGetURL($ele, $Text)
    {
        $accessKeyId = ACCESSKEYID;
        $accessKeySecret = ACCESSKEYSECRET;
        $endpoint = 'http://oss-cn-beijing.aliyuncs.com'; // 例如：'http://oss-cn-beijing.aliyuncs.com'
        $bucketName = 'dexcimg'; // 您的OSS存储桶名称
        // $expiration = 3600; // URL有效期，这里设置为1小时（3600秒）

        try {
            // 创建OSS客户端实例
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            // 获取上传的文件信息
            // $file = $_FILES['file'];
            $localFilePath = $ele; // 上传的临时文件路径或者远程图片
            $objectKey =  $Text; // 设置在OSS上的文件名，这里将其放在user_uploads目录下
            // 设置文件上传的选项，例如：设置文件公共读权限

            $options = [
                OssClient::OSS_HEADERS => [
                    'Content-Type' => 'image/jpg',
                    'x-oss-object-acl' => 'public-read',
                ],
            ];
            // 执行文件上传
            $ossClient->uploadFile($bucketName, $objectKey, $localFilePath, $options);
            // 获取上传文件的URL
            $publicUrl = $ossClient->signUrl($bucketName, $objectKey, null);
            return 'https://dexcimg.oss-cn-beijing.aliyuncs.com/' . $objectKey;
        } catch (OssException $e) {
            // echo 'Error: ' . $e->getMessage();
            return   false;
        }
    }
    //获取访问地址(课件)
    function Getfileaccessaddress($object)
    {
        $accessKeyId = ACCESSKEYID;
        $accessKeySecret = ACCESSKEYSECRET;
        $endpoint = 'http://oss-cn-beijing.aliyuncs.com'; // 例如：'http://oss-cn-beijing.aliyuncs.com'
        $bucketName = 'dexccourseware'; // 您的OSS存储桶名称
        $timeout = 60;
        try {
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            $options = array(
                "response-content-disposition" => "attachment",
            );

            $signedUrl = $ossClient->signUrl($bucketName, $object, $timeout, 'GET', $options);
            return $signedUrl;
        } catch (OssException $e) {
            // echo 'Error: ' . $e->getMessage();
            return   false;
        }
    }


    // 阿里云删除
    function deleteOSSFile($ele, $bucket = 'dexcimg')
    {
        $accessKeyId = ACCESSKEYID;
        $accessKeySecret = ACCESSKEYSECRET;
        $endpoint = 'http://oss-cn-beijing.aliyuncs.com'; // 例如：'http://oss-cn-beijing.aliyuncs.com'
        $bucketName = $bucket; // 您的OSS存储桶名称
        $objectKey = $ele; // 要删除的文件的对象键（路径）
        try {
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);

            // 执行删除操作

            $ossClient->deleteObject($bucketName, $objectKey);

            return true;
        } catch (OssException $e) {
            return false;
        }
    }

    // 新的思路 加解密后  创建一个数据表  加密后 存入返回KEY即可    解密时候查询
    // 视频点播上传授权
    public function handleChunkedUpload()
    {

        $data = json_decode(file_get_contents('php://input'), true);
        $accessKeyId = ACCESSKEYID;
        $accessKeySecret = ACCESSKEYSECRET;
        $regionId = 'cn-beijing';

        AlibabaCloud::accessKeyClient($accessKeyId, $accessKeySecret)
            ->regionId($regionId)
            ->asDefaultClient();

        $filename = $data['vidoname'];
        $response = Vod::v20170321()->CreateUploadVideo()
            ->withTitle($filename)
            ->withFileName($filename)
            ->request();

        $uploadAddress = json_decode(base64_decode($response['UploadAddress']), true);
        $uploadAuth = json_decode(base64_decode($response['UploadAuth']), true);
        $videoId = $response['VideoId'];
        //存好用户名和视频ID  就返回授权
        //备用ID  正式ID
        return ['uploadAddress' => $uploadAddress, 'uploadAuth' => $uploadAuth, 'videoId' => $videoId];
    }
    // 文件上传下载授权  限制了路径  保证了文件的绝对安全性 重复攻击  他也永远上传的是同一个文件
    public function Fileuploadauthorization($path, $bucket, $type = 1)
    {
        // TYPE 1等于上传   2等于下载  只给一个权限  防止私钥被用作其他
        $accessKeyId = 'LTAI5t7btPWJTiXB6WDqayPq';
        $accessKeySecret = 'R2iHhCWPqXzkGZNxwE6yGzVPR3u2B6';
        try {
            $config = new Config([
                'accessKeyId' => $accessKeyId,
                'accessKeySecret' => $accessKeySecret
            ]);
            $config->endpoint = "sts.cn-beijing.aliyuncs.com";
            $client =  new Sts($config);
            $assumeRoleRequest = new AssumeRoleRequest([
                "roleArn" => "acs:ram::1019247211553594:role/dexcpro",
                "roleSessionName" => "sessiontest",
                "durationSeconds" => 900,
                "policy" => '{
                    "Version": "1",
                    "Statement": [
                      {
                        "Effect": "Allow",
                        "Action": [
                           "' . ($type == 1 ? 'oss:PutObject' : 'oss:GetObject') . '"
                        ],
                        "Resource": "acs:oss:*:*:' . $bucket . '/' . $path . '"
                      }
                    ]
                  }'
            ]);
            // 下面6行是测试用的 可以删  这个$runtime 表示最多三个链接  10秒内操作有效
            $runtime = new RuntimeOptions([]);
            $response = $client->assumeRole($assumeRoleRequest, $runtime);
            // $result = $client->assumeRoleWithOptions($assumeRoleRequest, $runtime);
            return $response->body->credentials;
        } catch (\Exception $e) {
            return false;
        }
    }

    // 获取视频信息
    public function getPlayInfo($videoId)
    {

        $accessKeyId = ACCESSKEYID;
        $accessKeySecret = ACCESSKEYSECRET;
        $regionId = 'cn-beijing';

        // 初始化VOD客户端
        AlibabaCloud::accessKeyClient($accessKeyId, $accessKeySecret)
            ->regionId($regionId)
            ->asDefaultClient();

        try {
            // 使用VOD SDK获取视频信息
            $response = Vod::v20170321()->GetVideoInfo()
                ->withVideoId($videoId)
                ->format('JSON')
                ->request();

            // 从响应中获取视频信息
            return $response['Video']['Duration'];
        } catch (\Throwable $th) {
            return 'ServerException: ' . $th->getErrorMessage();
        }
    }
    // 删除视频
    public function DeleteVideo($videoId)
    {

        $accessKeyId = ACCESSKEYID;
        $accessKeySecret = ACCESSKEYSECRET;
        $regionId = 'cn-beijing';


        // 初始化VOD客户端
        AlibabaCloud::accessKeyClient($accessKeyId, $accessKeySecret)
            ->regionId($regionId)
            ->asDefaultClient();

        try {

            $videoIdsString = implode(',', $videoId);
            $request = Vod::v20170321()->deleteVideo()
                ->withVideoIds($videoIdsString)
                ->format('JSON')
                ->request();
            return $request->toArray();
        } catch (ClientException $e) {
            return [];
        } catch (ServerException $e) {
            return [];
        }
    }
    // 获取播放地址有时效的
    public function initVodClient()
    {
        $videoId = json_decode(file_get_contents('php://input'), true);
        $accessKeyId = ACCESSKEYID;
        $accessKeySecret = ACCESSKEYSECRET;
        $regionId = 'cn-beijing';
        $expirationTime = 86400; //有效期24小时
        try {
            // 判断有没有购买
            // 初始化VOD客户端
            AlibabaCloud::accessKeyClient($accessKeyId, $accessKeySecret)
                ->regionId($regionId)
                ->asDefaultClient();


            // 使用VOD SDK获取视频播放地址
            $response = Vod::v20170321()->GetPlayInfo()
                ->withVideoId($videoId)
                ->withAuthTimeout($expirationTime)
                ->format('JSON')
                ->request();

            // 从响应中获取所有播放地址信息

            $playInfoList = $response['PlayInfoList']['PlayInfo'];
            foreach ($playInfoList as $value) {
                if ($value['Definition'] == 'OD') {
                    $playInfoList = $value['PlayURL'];
                }
                # code...
            }
            echo json_encode(retur('成功', $playInfoList));
        } catch (ClientException $e) {
            echo json_encode(retur('失败', $e, 908));
        }
    }
    // 获取充值地址
    public function GetWalletAddress()
    {
        $user = self::testandverify();
        $userid = $user['id'];
        // 查询当前用户是否存在钱包地址
        // 获取当前时间戳
        // 设置时区为新加坡
        try {
            date_default_timezone_set('Asia/Singapore');
            // 获取新加坡时区的当前时间戳
            $currentTimestampSingapore = time();
            $minutesAgo = 30 * 60; // 30分钟 = 30 * 60秒
            $timestamp30MinutesAgo = $currentTimestampSingapore - $minutesAgo;
            // 数据库存的是以前时间
            // 过期时间是数据库的加上30分钟
            //3+30=过期时间   过期时间要大于当前时间  当前时间减去30分钟  当前12-30fenzhong   11:30   数据库 11：:45
            // $arr = $this->model->fetchPage('dex_walletaddress', 'id', 0, 1000, 'ASC',  ['userid' => $userid, 'id >' => 0]);
            $address = $this->model->onfetch('*', 'dex_walletaddress', 1, ['userid' => $userid, 'starttime >' => $timestamp30MinutesAgo])['data'];
            ignore_user_abort(true);
            set_time_limit(0);
            if (!$address) {
                // 获取一个新的钱包地址
                $address = $this->model->onfetch('*', 'dex_walletaddress', 1, ['userid' => '0'])['data']['address'];
                $web3 = new Web3('https://data-seed-prebsc-1-s1.binance.org:8545');
                $abi = json_decode($this->model->onfetch('*', 'dex_abi', 1, ['name' => 'erc20'])['data']['abi'], true);
                $contract = new Contract($web3->provider, $abi);
                // 查询精度
                $contract->at('0xDD2f7682429EBf4818eacC8C50102d0DA8772900')->call('decimals', $this->myCallback);
                $decimals = $this->myCallback->result[0]->value;
                // 查询余额
                $contract->at('0xDD2f7682429EBf4818eacC8C50102d0DA8772900')->call('balanceOf', $address, $this->myCallback);
                // 处理结果(可能每个代币都不一样，到时候需要修改的)
                $balance =  $this->myCallback->result['balance']->value;
                $balance = $balance / (10 ** $decimals);
                date_default_timezone_set('Asia/Singapore');
                // 获取新加坡时区的当前时间戳
                $starttime = time();
                $arr = $this->model->onchange('dex_walletaddress', ['userid' => $userid, 'starttime' => $starttime, 'balance' =>  $balance], ['address' => $address]);
                // 添加一个记录
                // 记录表    ID  分类  操作  内容  时间戳
                $adv = $this->model->sqladd('dex_record', ['userid' => $userid, 'type' => '1', 'operation' => '获取充值地址', 'value' => $address, 'timestamp' => $starttime]);
                // 修改这个钱包的用户ID 开始时间
                echo json_encode(retur($adv, $address));
            } else {
                echo json_encode(retur('成功',  $address['address']));
            }
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage();
            $errorLine = $th->getLine();
            echo   json_encode(retur('程序错误', "错误信息：{$errorMessage}，发生在第 {$errorLine} 行。", 3001));
        }
    }
    //充值监控
    public function RechargeMonitoring()
    {
        // 首先是采集当前拥有账号的钱包地址
        ignore_user_abort(true);
        set_time_limit(0);
        $address = $this->model->onfetch('*', 'dex_walletaddress', 9, ['userid >' => 0]);
        if ($address['code'] == 200) {
            // print_r($address);
            //遍历查询
            foreach ($address['data'] as $key => $value) {
                //查询每一个的余额
                $web3 = new Web3('https://data-seed-prebsc-1-s1.binance.org:8545');
                $abi = json_decode($this->model->onfetch('*', 'dex_abi', 1, ['name' => 'erc20'])['data']['abi'], true);
                $contract = new Contract($web3->provider, $abi);
                // 查询余额
                $contract->at('0xDD2f7682429EBf4818eacC8C50102d0DA8772900')->call('balanceOf', $value['address'], $this->myCallback);
                // 处理结果(可能每个代币都不一样，到时候需要修改的)
                $balance =  $this->myCallback->result['balance']->value;
                $balance = $balance / (10 ** 18);
                echo '钱包地址' . $value['address'];
                echo '<pre>';
                echo '最新余额' . $balance;
                echo '<pre>';
                echo '钱包余额' . $value['balance'];
                echo '<pre>';
                // 计算充值金额
                $amount =  bcsub($balance, $value['balance'], 18);
                echo '充值金额' . $amount;
                if ($amount > 0) {
                    // 用户充值了
                    // 查询用户余额
                    $userinfo = $this->model->onfetch('*', 'dex_user', 1, ['id' => $value['userid']])['data'];
                    // 必须保证成功
                    if ($userinfo) {
                        //计算新的余额
                        $Rechargeamount = bcadd($userinfo['balance'], $amount, 18);
                        // 修改余额
                        $this->model->onchange('dex_user', ['balance' => $Rechargeamount], ['id' => $value['userid']]);
                        // 修改钱包的余额
                        $this->model->onchange('dex_walletaddress', ['balance' =>  $balance], ['address' =>  $value['address']]);
                        // 增加记录
                        date_default_timezone_set('Asia/Singapore');
                        // 获取新加坡时区的当前时间戳
                        $starttime = time();
                        $notes = ['address' => $value['address'], 'Rechargeamount' => $amount, 'Balancebeforerecharge' => $userinfo['balance'], 'Balanceafterrecharge' => $Rechargeamount];
                        $this->model->sqladd('dex_record', ['notes' => $notes, 'userid' => $value['userid'], 'type' => '2', 'operation' => '用户充值', 'value' => $amount, 'timestamp' => $starttime]);
                    }
                } else {
                    // 检查是否超过35分钟 是就清空钱包中的时间戳和用户ID
                    date_default_timezone_set('Asia/Singapore');
                    // 获取新加坡时区的当前时间戳
                    $currentTimestampSingapore = time();
                    $minutesAgo = 35 * 60; // 35分钟 = 35 * 60秒
                    // 计算时间  当前的时间戳减去35分钟   开始时间   11:30  过期时间12:00-29=11:31 没过期 时间戳大于开始时间   13:00-30=12:30过期了 时间戳大于开始时间
                    $timestamp30MinutesAgo = $currentTimestampSingapore - $minutesAgo;
                    echo '<pre>';
                    echo $value['starttime'];
                    echo '<pre>';
                    echo  $timestamp30MinutesAgo;
                    echo '<pre>';
                    //当前时间如果大于过期时间-35分钟  name就是过期了
                    if ($value['starttime'] < $timestamp30MinutesAgo) {
                        //
                        $this->model->onchange('dex_walletaddress', ['userid' =>  '0', 'starttime' => 0], ['address' =>  $value['address']]);
                    }
                }
                //余额减去数据库中的余额 大于0的话 就是充值的余额
                // 大于0  更新当前数据库中的余额
                // 修改用户的余额  用户的余额 加充值的余额
                // 增加记录
                # code...
                // 归集仅归集目前没有ID的钱包地址
                // 归集时禁止用户获取新钱包地址
            }
        }
    }
    // 需要有一个钱包查询地址
    // 判断当前地址是否拥有钱包   有就直接返回地址
    // 如果没有那么就分配一个地址 并且记录当前地址分配给谁 什么时间分配的
    // 还有一个更新充值  每分钟执行一次  获取当前有效地址  并依次获取金额
    // 更新钱包地址并添加到数据库
    public function getaddress()
    {
        ignore_user_abort(true);
        set_time_limit(0);
        fastcgi_finish_request();
        // 连接到以太坊节点
        $contractAbi = json_decode($this->model->onfetch('*', 'dex_abi', 1, ['name' => 'recharge'])['data']['abi'], true);
        $web3 = new Web3('https://data-seed-prebsc-1-s1.binance.org:8545');
        $contract = new Contract($web3->provider, $contractAbi);
        // 指定合约地址
        $contractAddress = '0xe9E7bbDB4caC792470d175C9877846a5744CeCfB';
        // 指定方法名称和参数
        $methodName = 'getDeployedContractsCount';
        try {
            // 调用合约方法
            $contract->at($contractAddress)->call($methodName, $this->myCallback);
            // 处理结果
            $endstr =  $this->myCallback->result[0]->value;
            $oldtime =  $this->model->onfetchmax('*', 'dex_walletaddress', [], 'uid');
            $strt = 0;
            if ($oldtime['code'] == 200) {
                $strt = intval($oldtime['data']['uid']) + 1;
            }
            for ($i = $strt; $i < $endstr / 1; $i++) {
                $contract->at($contractAddress)->call('deployedContracts', $i, $this->myCallback);
                $address = $this->model->onfetch('*', 'dex_walletaddress', 1, ['address' => $this->myCallback->result[0]]);
                if ($address['code'] == 200 && !$address['data']) {
                    $this->model->sqladd('dex_walletaddress', ['address' => $this->myCallback->result[0], 'uid' => $i]);
                }
            }
        } catch (\Exception $e) {
            // 处理任何异常，例如找不到方法
            return retur('失败', $e->getMessage(), 901);
        }
    }
    // 哈希解析
    public function  getTransaction()
    {

        $contractAbi = json_decode($this->model->onfetch('*', 'dex_abi', 1, ['name' => 'recharge'])['data']['abi'], true);
        $web3 = new Web3('https://data-seed-prebsc-1-s1.binance.org:8545');
        $contract = new Contract($web3->provider, $contractAbi);
        // var_dump(get_class_methods($contract));
        // print_r(get_class_methods($contract));
        // 解析哈希  下面两个都是
        $web3->eth->getTransactionByHash('0x2ae9b9819c91d3a3917a069b1f615a81fe40bf6ad152b25ab9dc3629393529a1', $this->myCallback);
        // $web3->eth->getTransactionReceipt('0xe00693b20b81ada9edbe2012aa370cfa105e37fa7f7fb88137550c1fb4b96e7e', $this->myCallback);
        // var_dump($this->myCallback->result);
        // var_dump($this->myCallback->result->gas);
        // var_dump($this->myCallback->result->gasPrice);
        $inputData = $this->myCallback->result->input;

        $methodSignature = substr($inputData, 0, 10); // 10 个字符，前四个字节的方法签名

        // 如果你有智能合约的 ABI，可以使用 Web3.php 来解析输入参数
        $contract = $web3->eth->contract($contractAbi)->at($this->myCallback->result->from);
        $methodName = $contract->decodeMethod($methodSignature); // 解码方法签名

        // 解析输入参数
        $inputParams = substr($inputData, 10); // 剩余的输入参数数据
        $decodedParams = $contract->decodeParameters($methodName, $inputParams);
    }
    // 后台转账  可以用了
    public function setsend()
    {
        // 合约地址和 ABI $this->model->onfetch('*', 'dex_Settings', 1, ['id' => '1'])['data']['walletaddress']
        $contractAddress = $this->model->onfetch('*', 'dex_Settings', 1, ['id' => '1'])['data']['walletaddress']; // 合约地址
        $from = '0xC274c98D8db463bd520ac8066F2954aeD569Ac72';
        $privateKey = '0x83165536a3c7a0ed8b139876a5075c0cb2ecdace2244f05e8be386589e3ef392';

        $contractAbi = json_decode($this->model->onfetch('*', 'dex_abi', 1, ['name' => 'recharge'])['data']['abi'], true);
        $web3 = new Web3('https://data-seed-prebsc-1-s1.binance.org:8545');
        // 创建合约对象
        $contract = new Contract($web3->provider, $contractAbi);
        // 构建交易参数
        $functionName = 'createNewTokenWithdrawalContract'; // 替换为合约中的函数名
        $contractData = '0x' . $contract->at($contractAddress)->getData($functionName, 5);
        // 获取当前GAS价格
        $web3->eth->gasPrice($this->myCallback);
        $gasPrice = '0x' . $web3->utils->toHex($this->myCallback->result->value);
        // 获取发件人的 nonce
        $web3->eth->getTransactionCount($from, $this->myCallback);
        $nonce = '0x' . $web3->utils->toHex($this->myCallback->result->value);
        // 需要发送的以太币价值
        $value = '0x0';
        // 估算 gas
        $transactionData = [
            'from' => $from,
            'to' => $contractAddress,
            'value' => $value, // 以 Wei 为单位的金额
            'data' => $contractData,
        ];
        // 估算 gas
        $web3->eth->estimateGas($transactionData, $this->myCallback);
        $gas = '0x' . $web3->utils->toHex($this->myCallback->result->value);
        // 设置交易参数
        $transactionData['nonce'] = $nonce;
        $transactionData['gas'] = $gas;
        $transactionData['gasPrice'] = $gasPrice;
        $transactionData['chainId'] = 97;
        // NEW交易实例
        $transaction = new Transaction($transactionData);
        // 签名
        $transaction->sign($privateKey);
        $hashedTx = $transaction->serialize();
        dump($hashedTx);
        echo '<br>新的';
        //发送交易到链上
        $web3->eth->sendRawTransaction('0x' . $hashedTx, $this->myCallback);
        $tx = $this->myCallback->result;
        dump($tx);
        echo $tx . '<br>后';
        // 调用合约方法
        $contract->at($contractAddress)->call('getDeployedContractsCount', $this->myCallback);
        $tx = $this->myCallback->result[0]->value;
        var_dump($tx);
        echo '前<br>';
    }
    public function Imputation()
    {
        // 合约地址和 ABI $this->model->onfetch('*', 'dex_Settings', 1, ['id' => '1'])['data']['walletaddress']
        $contractAddress = $this->model->onfetch('*', 'dex_Settings', 1, ['id' => '1'])['data']['walletaddress']; // 合约地址
        $from = '0xC274c98D8db463bd520ac8066F2954aeD569Ac72';
        $privateKey = '0x83165536a3c7a0ed8b139876a5075c0cb2ecdace2244f05e8be386589e3ef392';

        $contractAbi = json_decode($this->model->onfetch('*', 'dex_abi', 1, ['name' => 'recharge'])['data']['abi'], true);
        $web3 = new Web3('https://data-seed-prebsc-1-s1.binance.org:8545');
        // 创建合约对象
        $contract = new Contract($web3->provider, $contractAbi);
        // 构建交易参数
        $functionName = 'withdrawTokenFromContracts'; // 替换为合约中的函数名
        // 获取归集地址
        $arr =   $this->model->onfetch('*', 'dex_walletaddress', 9, ['balance >' => '0'])['data'];
        $addresses = array_column($arr, 'address');
        var_dump($addresses);
        // 代币地址
        $token = '0xDD2f7682429EBf4818eacC8C50102d0DA8772900';
        // 提取到哪个地址
        $to = '0xC274c98D8db463bd520ac8066F2954aeD569Ac72';

        $contractData = '0x' . $contract->at($contractAddress)->getData($functionName, $addresses, $token, $to);
        // 获取当前GAS价格
        $web3->eth->gasPrice($this->myCallback);
        $gasPrice = '0x' . $web3->utils->toHex($this->myCallback->result->value);
        // 获取发件人的 nonce
        $web3->eth->getTransactionCount($from, $this->myCallback);
        $nonce = '0x' . $web3->utils->toHex($this->myCallback->result->value);
        // 需要发送的以太币价值
        $value = '0x0';
        // 估算 gas
        $transactionData = [
            'from' => $from,
            'to' => $contractAddress,
            'value' => $value, // 以 Wei 为单位的金额
            'data' => $contractData,
        ];
        // 估算 gas
        $web3->eth->estimateGas($transactionData, $this->myCallback);
        $gas = '0x' . $web3->utils->toHex($this->myCallback->result->value);
        // 设置交易参数
        $transactionData['nonce'] = $nonce;
        $transactionData['gas'] = $gas;
        $transactionData['gasPrice'] = $gasPrice;
        $transactionData['chainId'] = 97;
        // NEW交易实例
        $transaction = new Transaction($transactionData);
        // 签名
        $transaction->sign($privateKey);
        $hashedTx = $transaction->serialize();
        dump($hashedTx);
        echo '<br>';
        //发送交易到链上
        $web3->eth->sendRawTransaction('0x' . $hashedTx, $this->myCallback);
        $tx = $this->myCallback->result;
        dump($tx);
        echo $tx . '<br>后';
        // 调用合约方法
        $contract->at($contractAddress)->call('getDeployedContractsCount', $this->myCallback);
        $tx = $this->myCallback->result[0]->value;
        var_dump($tx);
        echo '前<br>';
    }
    //  获取未领取的优惠卷
    public function Unclaimedvouchers()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $userid =  self::testandverify();
        $coupon = array_merge($this->model->onfetch('*', 'dex_coupons', 9, ['Category' => '0', 'redeem_method' => 2])['data'],  $this->model->onfetch('*', 'dex_coupons', 9, ['Category' => $data['Category'], 'ProductID' => ['0', $data['ProductID']], 'redeem_method' => 2])['data']);
        // $coupon =  $this->model->onfetch('*', 'dex_coupons', 1, ['Category' => ['0']]);
        $milliseconds = round(microtime(true) * 1000);
        foreach ($coupon as $key => $value) {
            # code...

            if (($milliseconds >= $value['redeem_start_time'] || $value['redeem_start_time'] == 0) && ($milliseconds <= $value['redeem_end_time'] || $value['redeem_end_time'] == 0)) {
                //可以领取
                $coupon[$key]['redeem_start_time'] = $coupon[$key]['redeem_start_time'] > 0 ? date("Y/m/d H:i:s", $value['redeem_start_time'] / 1000) : '不限时';
                $coupon[$key]['redeem_end_time'] = $coupon[$key]['redeem_end_time'] > 0 ? date("Y/m/d H:i:s", $value['redeem_end_time'] / 1000) : '不限时';
                $coupon[$key]['redeem'] = true;
                $coupon[$key]['time'] = false;
                if (($milliseconds >= $value['start_time'] || $value['start_time'] == 0) && ($milliseconds <= $value['end_time'] || $value['end_time'] == 0)) {
                    // 在促销期
                    $coupon[$key]['start_time'] = date("Y/m/d H:i:s", $value['start_time'] / 1000);
                    $coupon[$key]['end_time'] = date("Y/m/d H:i:s", $value['end_time'] / 1000);
                    $coupon[$key]['time'] = true;
                }
                $coupon[$key]['receive'] = false;
                if ($this->model->onfetch('*', 'dex_couponskey', 1, ['coupid' => $value['id'], 'userid' => $userid['id']])['data']) {
                    $coupon[$key]['receive'] = true;
                }
            } else {
                unset($coupon[$key]);
            }
        }
        echo json_encode(retur('成功', $coupon));
        //先获取所有的券
        // 然后挨着判断可以领取的
    }

    public function setreceive()
    {
        //先判断这个券是不是用户领取
        $data = json_decode(file_get_contents('php://input'), true);
        $test = self::testandverify();
        try {
            $coupon =  $this->model->onfetch('*', 'dex_coupons', 1, ['id' => $data])['data'];
            if ($coupon && $coupon['redeem_method'] == 2) {
                // 可以领取
                $key =  Uuid::uuid4();
                $new_string = str_replace('-', '', $key);
                $arr = $this->model->sqladd('dex_couponskey', ['coupid' => $data, 'coupkey' => $new_string, 'userid' => $test['id']]);
                echo json_encode($arr);
            } else {
                return retur('失败', '非法访问', 901);
            }
        } catch (\Throwable $th) {
            return retur('失败', $th->getMessage(), 901);
        }
    }


    // 课程内容
    public function Coursecontent()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        // $data = 17;
        // 通过课程ID  获取课程内容
        $CourseTable =  $this->model->onfetch('*', 'dex_CourseTable', 1, ['id' => $data]);

        //获取优惠卷列表
        $coupon = array_merge($this->model->onfetch('*', 'dex_coupons', 9, ['Category' => '0', 'redeem_method' => 2])['data'],  $this->model->onfetch('*', 'dex_coupons', 9, ['Category' => '1', 'ProductID' => ['0', $data], 'redeem_method' => 2])['data']);
        // $coupon =  $this->model->onfetch('*', 'dex_coupons', 1, ['Category' => ['0']]);
        $milliseconds = round(microtime(true) * 1000);
        foreach ($coupon as $key => $value) {
            # code...

            if (($milliseconds >= $value['redeem_start_time'] || $value['redeem_start_time'] == 0) && ($milliseconds <= $value['redeem_end_time'] || $value['redeem_end_time'] == 0)) {
                // 领取时间
                $coupon[$key]['redeem'] = true;
                $coupon[$key]['time'] = false;
                if (($milliseconds >= $value['start_time'] || $value['start_time'] == 0) && ($milliseconds <= $value['end_time'] || $value['end_time'] == 0)) {
                    // 在促销期
                    $coupon[$key]['time'] = true;
                }
            } else {
                unset($coupon[$key]);
            }
        }


        if ($CourseTable['code'] == 200) {

            $CourseTable = $CourseTable['data'];




            $CourseTable['coupon'] = $coupon;
            $CourseTable['user'] = $this->model->onfetch('*', 'dex_user', 1, ['id' => $CourseTable['admin']])['data'];
            unset($CourseTable['user']['password'], $CourseTable['user']['balance'], $CourseTable['user']['integral']);
            $CourseTable['saleperiod'] = false;
            if ($CourseTable['Discountstart'] && $CourseTable['DiscountEnd']) {
                //促销是否有效  生成时间戳
                $milliseconds = round(microtime(true) * 1000);
                if ($milliseconds >= $CourseTable['Discountstart'] && $milliseconds <= $CourseTable['DiscountEnd']) {
                    // 在促销期
                    $CourseTable['saleperiod'] = true;
                }
            }
            $CourseTable['lecturer'] = json_decode($CourseTable['lecturer']);
            foreach ($CourseTable['lecturer'] as $key => $value) {
                //获取讲师信息
                $CourseTable['lecturer'][$key] =   $this->model->onfetch('*', 'dex_user', 1, ['id' => $value])['data'];
                // 查询用户的帖子数量
                $CourseTable['lecturer'][$key]['posts'] = $this->model->getTotalRowCount('dex_CourseTable', ['admin' => $value]);
                // 问答数量
                $CourseTable['lecturer'][$key]['qanum'] = $this->model->getTotalRowCount('dex_issueslist', ['userid' => $value]);
                // 粉丝数量
                $CourseTable['lecturer'][$key]['fans'] = $this->model->getTotalRowCount('dex_follow', ['toid' => $value]);
                $CourseTable['lecturer'][$key]['posts'] = $CourseTable['lecturer'][$key]['posts'] ? $CourseTable['lecturer'][$key]['posts'] : 0;
                $CourseTable['lecturer'][$key]['qanum'] = $CourseTable['lecturer'][$key]['qanum'] ? $CourseTable['lecturer'][$key]['qanum'] : 0;
                $CourseTable['lecturer'][$key]['qanum'] = $CourseTable['lecturer'][$key]['qanum'] ? $CourseTable['lecturer'][$key]['qanum'] : 0;

                $CourseTable['lecturer'][$key]['UserGroupname'] = $this->model->onfetch('*', 'dex_UserGroup', 1, ['id' => $CourseTable['lecturer'][$key]['UserGroup']])['data']['name'];
                // 删除敏感信息
                unset($CourseTable['lecturer'][$key]['password'], $CourseTable['lecturer'][$key]['balance'], $CourseTable['lecturer'][$key]['integral']);
            }
            // 获取课程信息
            $ChapterTable =   $this->model->onfetch('*', 'dex_ChapterTable', 9, ['course' => $CourseTable['id']]);
            if ($ChapterTable['code'] == 200) {
                $CourseTable['course'] = $ChapterTable['data'];
                foreach ($CourseTable['course'] as $key => $value) {
                    $CourseTable['course'][$key]['children'] = $this->model->onfetch('*', 'dex_VideoTable', 9, ['chapter' =>  $value['id']])['data'];
                    $CourseTable['course'][$key]['courseware'] = $this->model->onfetch('*', 'dexc_courseware', 9, ['chapter' =>  $value['id']])['data'];
                }
            }
            // 解析内容
            $CourseTable['content']  = json_decode($CourseTable['content'], true);
            echo json_encode(retur('成功', $CourseTable));
        }
    }
    public function mail($to, $name, $title, $content)
    {
        $mail = new PHPMailer();
        try {
            $mail = new PHPMailer();
            // 配置邮件服务器设置
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->isSMTP(); // 使用 SMTP 发送
            $mail->Host = 'smtphz.qiye.163.com'; // 设置 SMTP 服务器地址
            $mail->SMTPAuth = true; // 启用 SMTP 认证
            $mail->Username = 'administrator@dexc.pro'; // SMTP 用户名
            $mail->Password = 'Woaini1.'; // SMTP 密码
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            // $mail->SMTPSecure = 'tls'; // 加密类型
            $mail->Port = 465; // 设置 SMTP 端口号



            // 设置邮件内容
            $mail->setFrom('administrator@dexc.pro', 'DEXC管理员'); // 发件人邮箱和姓名
            $mail->addAddress($to, $name); // 收件人邮箱和姓名
            $mail->isHTML(true);
            $mail->Subject = $title; // 邮件主题
            $mail->Body =  $content; // 邮件正文
            return   $mail->send();
        } catch (Exception $e) {
            return  $mail->ErrorInfo;
        }
    }
    public function verifyMessage()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $message = $data['message'];
        $signature = $data['signature'];
        try {
            // echo '开始?';
            $ethAddress = ETHverifyMessage($message, $signature);
            echo json_encode(retur('功', $ethAddress));
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage();
            $errorLine = $th->getLine();
            json_encode(retur('程序错误', "错误信息：{$errorMessage}，发生在第 {$errorLine} 行。", 3001));
        }

        // https://github.com/yanlongma/push
    }
}


	//                    _ooOoo_
	//                   o8888888o
	//                   88" . "88
	//                   (| -_- |)
	//                   O\  =  /O
	//                ____/`---'\____
	//              .'  \\|     |//  `.
	//             /  \\|||  :  |||//  \
	//            /  _||||| -:- |||||-  \
	//            |   | \\\  -  /// |   |
	//            | \_|  ''\-/''    |   |
	//            \  .-\__  `-`  ___/-. /
	//          ___`. .'  /-.-\  `. . __
	//       ."" '<  `.___\_<|>_/___.'  >'"".
	//      | | :  `- \`.;`\ _ /`;.`/ - ` : | |
	//      \  \ `-.   \_ __\ /__ _/   .-` /  /
	// ======`-.____`-.___\_____/___.-`____.-'======
	//                    `=-='
//                       .::::.
//                     .::::::::.
//                    :::::::::::
//                 ..:::::::::::'
//              '::::::::::::'
//                .::::::::::
//           '::::::::::::::..
//                ..::::::::::::.
//              ``::::::::::::::::
//               ::::``:::::::::'        .:::.
//              ::::'   ':::::'       .::::::::.
//            .::::'      ::::     .:::::::'::::.
//           .:::'       :::::  .:::::::::' ':::::.
//          .::'        :::::.:::::::::'      ':::::.
//         .::'         ::::::::::::::'         ``::::.
//     ...:::           ::::::::::::'              ``::.
//    ````':.          ':::::::::'                  ::::..
//                       '.:::::'                    ':'````..
//