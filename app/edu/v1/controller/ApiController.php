<?php

namespace app\edu\v1\controller;


use Ramsey\Uuid\Uuid;
use function common\ETHverifyMessage;
use function common\dump;
use function common\retur;
use common\v1Controller;

class ApiController extends v1Controller
{

    public function too()
    {
        $categorylist = $this->model->onfetch('*', 'dex_ification', 9, ['pid' => 0]);
        // $this->view->render(null, ['users' => $categorylist]);
        echo json_encode($categorylist);
        dump(ACCESSKEYID);
    }
    // 获取问答分类
    public function getqacat()
    {

        $categorylist = $this->model->onfetch('*', 'dex_ification', 9, ['pid' => 0]);

        echo json_encode($categorylist);
    }
    // 后台  获取文章分类
    public  function category()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $categorylist = $this->model->onfetch('*', $data, 9, '')['data'];
        $nearr = $this->Toquill->treearray(array_column($categorylist, null, 'id'));
        $catlist = $this->Toquill->showTree($nearr);
        echo json_encode(retur('成功', $catlist));
    }
    // 修改分类
    public function  Modifyarticleclassification()
    {
        // 修改原条件
        $data = json_decode(file_get_contents('php://input'), true);

        $test = self::testandverify();
        if (isset($test) && $test['administrators'] == 1) {

            //需要表名
            $from = $data['from'];
            // 删除数组中的表名
            unset($data['from']);
            // 获取条件
            $condition = $data['condition'];
            $pid = $condition['id'];
            // 删除条件
            unset($data['condition']);
            // 发送表名 需要添加的数组
            $arr = $this->model->onchange($from, $data, $condition);
            // 修改下级分类
            $arrlist = $this->model->onfetch('*', $from, 9, '');
            $arrlist = $arrlist['data'];
            $net =  $this->Toquill->recursion($arrlist, $pid);
            $can = [];
            foreach ($net as  $value) {
                // 查询一下上级
                $level = $this->model->onfetch('*', $from, 1, ['id' => $value['pid']])['data'];
                $acarr =  $this->model->onchange($from, ['level' => $level['level'] / 1 + 1], ['id' => $value['id']]);
                if ($acarr['code'] != 200) {
                    $can[] = $acarr;
                }
            }
            echo json_encode($arr);
        } else {
            echo json_encode(retur('失败', '非法访问', 3000));
        }
    }
    //    获取下级分类
    public function Getification()
    {

        $data = json_decode(file_get_contents('php://input'), true);
        $catlist = [];
        $list = $this->model->onfetch('*', 'dex_category', 1, ['alias' => $data]);
        array_push($catlist, $list['data']);
        $arrlist = $this->model->onfetch('*', 'dex_category', 9, []);
        $arrlist = $arrlist['data'];
        $net = $this->Toquill->recursions($arrlist, $list['data']['id']);
        array_push($catlist, $net);
        echo json_encode(retur('成功le', $catlist));
    }


    // 获取文章分页模式
    public function getarticlepage()
    {
        //接收参数  页码  每页记录数  排序参数 倒序还是顺序
        //这样的话  首页  直接获取最近50条好了
        $data = json_decode(file_get_contents('php://input'), true);
        // 接收分页参数

        // 设置默认值
        $perPage = isset($data['perPage']) ? $data['perPage'] : 10; // 默认每页记录数为 10
        $page = isset($data['page']) ? $data['page'] : 1; // 默认第一页
        $offset = ($page - 1) * $perPage;
        $order = isset($data['offset']) ? $data['offset'] : 'DESC'; // 默认降序排序
        $thumbnailCondition = isset($data['thumbnail']) ? $data['thumbnail'] : '';
        $data['tosort'] = isset($data['tosort']) ? $data['tosort'] : 'time';
        // 根據哪個表排序
        //表分類 時間 time 人氣(瀏覽量)：popularity
        $lRowCount = 0;
        $orderByColumn = null;
        if ($data['tosort'] == 'time') {
            $orderByColumn = 'id';
        } elseif ($data['tosort'] == 'popularity') {
            $orderByColumn = 'PageView';
        }

        //按照时间  按照人气 不过这两点
        $arr = [];
        try {
            if ($data['id'] && !isset($data['previous']) && !isset($data['next'])) {
                $condition = ['id' => $data['id']];
            } else if ($data['alias']) {
                // 通过别名获取PID
                //获取自己的ID
                $catlist = [];
                $list = $this->model->onfetch('*', 'dex_category', 1, ['alias' => $data['alias']]);
                array_push($catlist, $list['data']['id']);
                $arrlist = $this->model->onfetch('*', 'dex_category', 9);
                $arrlist = $arrlist['data'];
                $catlist =  array_merge($catlist, $this->Toquill->recursionabs($arrlist, $list['data']['id']));
                // 需要一个或的条件
                $condition = ['pid' => $catlist];
            } else if ($data['userid']) {
                // 用户ID的帖子
                $condition = ['userid' => $data['userid']];
            } else if ($data['collect']) {
                $arrlist = $this->model->onfetch('postid', 'dex_collect', 9, ['userid' => $data['collect']])['data'];
                $condition = ['id' => array_column($arrlist, 'postid')];
                // 收藏
            } else if ($data['previous']) {
                //查询前一篇
                $condition = ['id <' => $data['id']];
                $order = 'DESC';
            } else if ($data['next']) {
                //查询后一篇
                $condition = ['id >' => $data['id']];
                $order = 'ASC';
            } else {
                // 没有条件  现在的问题是 如何计算前两个  现在获取了0-50  50-52
                $condition = [];
            }
            $arr = $this->model->fetchPage('dex_article', $orderByColumn, $offset, $perPage, $order, $condition, $thumbnailCondition);
            // echo json_encode(retur('未查询到数据', ['dex_article', $orderByColumn, $offset, $perPage, $order, $condition, $thumbnailCondition], 404));
            // exit;
            $lRowCount = $this->model->getTotalRowCount('dex_article', $condition);
            if ($arr['code'] == 200) {
                $newarr = [];
                foreach ($arr['data'] as $key => $value) {
                    $list = $value;
                    $list['Price'] = number_format($list['Price']);
                    $list['user'] =  $this->model->onfetch('*', 'dex_user', 1, ['id' => $list['userid']])['data'];
                    unset($list['user']['password'], $list['user']['balance'], $list['user']['integral']);
                    $cate = $this->model->onfetch('*', 'dex_category', 1, ['id' => $list['pid']])['data'];
                    $list['pidname'] = $cate['name'];
                    $list['alias'] = $cate['alias'];
                    $list['content'] = json_decode($list['content'], true);
                    $list['Like'] = $this->model->onfetch('*', 'dex_Like', 9, ['PostID' => $list['id']])['data'];
                    $list['collect'] = $this->model->onfetch('*', 'dex_collect', 9, ['PostID' => $list['id']])['data'];
                    array_push($newarr, $list);
                }
                echo json_encode(retur($lRowCount, $newarr));
            } else {
                echo json_encode(retur('未查询到数据', $arr['data'], 404));
            }
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage();
            $errorLine = $th->getLine();
            echo json_encode(retur('程序错误', "错误信息：{$errorMessage}，发生在第 {$errorLine} 行。", 5001));
        }
    }
    // 文章浏览随机数
    public function acbrowsing()
    {

        $data = json_decode(file_get_contents('php://input'), true);

        foreach ($data as $key => $value) {
            $this->model->onchange('dex_article', ['PageView' => $this->model->onfetch('PageView', 'dex_article', 1, ['id' => $value])['data']['PageView'] / 1 + mt_rand(1, 10)], ['id' => $value]);
        }
        echo json_encode(retur('成功', $data));
    }
    // 课程浏览随机数
    public function corebrowsing()
    {

        $data = json_decode(file_get_contents('php://input'), true);

        foreach ($data as $key => $value) {
            $this->model->onchange('dex_CourseTable', ['PageView' => $this->model->onfetch('PageView', 'dex_CourseTable', 1, ['id' => $value])['data']['PageView'] / 1 + mt_rand(1, 10)], ['id' => $value]);
        }
        echo json_encode(retur('成功', $data));
    }
    // 问答浏览随机数
    public function QAbrowsing()
    {

        $data = json_decode(file_get_contents('php://input'), true);
        foreach ($data as $key => $value) {
            $this->model->onchange('dex_issueslist', ['PageView' => $this->model->onfetch('PageView', 'dex_issueslist', 1, ['id' => $value])['data']['PageView'] / 1 + mt_rand(1, 10)], ['id' => $value]);
        }
        echo json_encode(retur('成功', $data));
    }
    // 获取充值记录(分页)
    public function RechargeRecord()
    {
        //接收参数  页码  每页记录数  排序参数 倒序还是顺序
        //这样的话  首页  直接获取最近50条好了
        $data = json_decode(file_get_contents('php://input'), true);
        // 接收分页参数
        $perPage = isset($data['perPage']) ? $data['perPage'] : 1; // 默认每页记录数为 10
        $page = isset($data['page']) ? $data['page'] : 1; // 默认第一页
        $offset = ($page - 1) * $perPage;
        $order = isset($data['offset']) ? $data['offset'] : 'DESC'; // 默认降序排序
        $condition = ['type' => 2];
        $arr = $this->model->fetchPage('dex_record', 'id', $offset, $perPage, $order, $condition);
        $lRowCount = $this->model->getTotalRowCount('dex_record', $condition);
        if ($arr['code'] == 200) {
            foreach ($arr['data'] as $key => $value) {
                if ($arr['data'][$key]['notes']) {
                    $arr['data'][$key]['notes'] = json_decode($value['notes'], true);
                    $arr['data'][$key]['notes']['address'] = strtoupper($arr['data'][$key]['notes']['address']);
                    $arr['data'][$key]['notes']['Rechargeamount'] =  bcadd($arr['data'][$key]['notes']['Rechargeamount'], 0, 3);
                    $arr['data'][$key]['notes']['Balanceafterrecharge'] =  bcadd($arr['data'][$key]['notes']['Balanceafterrecharge'], 0, 3);
                    $arr['data'][$key]['notes']['Balancebeforerecharge'] =  bcadd($arr['data'][$key]['notes']['Balancebeforerecharge'], 0, 3);
                }
                date_default_timezone_set('UTC');
                $arr['data'][$key]['tmime'] =  date("Y-m-d H:i:s e", $arr['data'][$key]['timestamp']);
                $arr['data'][$key]['value'] = bcadd($arr['data'][$key]['value'], 0, 3);
                $user  =  $this->model->onfetch('*', 'dex_user', 1, ['id' => $value['userid']])['data'];
                unset($user['password'], $user['balance'], $user['integral']);
                $arr['data'][$key]['user'] = $user;
            }


            echo json_encode(retur($lRowCount, $arr['data']));
        }
    }
    // 获取优惠卷分页
    public function getvoucher()
    {
        //接收参数  页码  每页记录数  排序参数 倒序还是顺序
        //这样的话  首页  直接获取最近50条好了
        $data = json_decode(file_get_contents('php://input'), true);
        // 接收分页参数
        $perPage = isset($data['perPage']) ? $data['perPage'] : 1; // 默认每页记录数为 10
        $page = isset($data['page']) ? $data['page'] : 1; // 默认第一页
        $offset = ($page - 1) * $perPage;
        $order = isset($data['offset']) ? $data['offset'] : 'DESC'; // 默认降序排序
        $condition = [];
        if (isset($data['condition'])) {
            $condition = $data['condition'];
        }

        $arr = $this->model->fetchPage('dex_coupons', 'id', $offset, $perPage, $order, $condition);
        $lRowCount = $this->model->getTotalRowCount('dex_coupons', $condition);
        if ($arr['code'] == 200) {
            echo json_encode(retur($lRowCount, $arr['data']));
        } else {
            echo json_encode(retur($lRowCount, $arr['data'], 404));
        }
    }
    // 获取优惠卷KEY
    public function getvoucherkey()
    {
        //接收参数  页码  每页记录数  排序参数 倒序还是顺序
        //这样的话  首页  直接获取最近50条好了
        $data = json_decode(file_get_contents('php://input'), true);
        // 接收分页参数
        $perPage = isset($data['perPage']) ? $data['perPage'] : 1; // 默认每页记录数为 10
        $page = isset($data['page']) ? $data['page'] : 1; // 默认第一页
        $offset = ($page - 1) * $perPage;
        $order = isset($data['offset']) ? $data['offset'] : 'DESC'; // 默认降序排序
        $condition = [];
        if (isset($data['condition'])) {
            $condition = $data['condition'];
        }
        $arr = $this->model->fetchPage('dex_couponskey', 'id', $offset, $perPage, $order, $condition);
        $lRowCount = $this->model->getTotalRowCount('dex_couponskey', $condition);
        if ($arr['code'] == 200) {
            echo json_encode(retur($lRowCount, $arr['data']));
        } else {
            echo json_encode(retur($lRowCount, $arr['data'], 404));
        }
    }
    // 创建优惠卷KEY
    public function setvoucherkey()
    {
        //接收参数  页码  每页记录数  排序参数 倒序还是顺序
        //这样的话  首页  直接获取最近50条好了
        $data = json_decode(file_get_contents('php://input'), true);
        // 接收分页参数
        $test = self::testandverify();
        if (isset($test) && $test['administrators'] == 1) {
            $key =  Uuid::uuid4();
            $new_string = str_replace('-', '', $key);
            $data['coupkey'] = $new_string;
            $arr = $this->model->sqladd('dex_couponskey', $data);
            echo json_encode($arr);
        } else {
            echo json_encode(retur('失败', '非法访问', 3000));
        }
    }
    // 获取问答分页模式
    public function getquestionpage()
    {
        //接收参数  页码  每页记录数  排序参数 倒序还是顺序
        //这样的话  首页  直接获取最近50条好了
        $data = json_decode(file_get_contents('php://input'), true);
        // 接收分页参数
        $perPage = isset($data['perPage']) ? $data['perPage'] : 1; // 默认每页记录数为 10
        $page = isset($data['page']) ? $data['page'] : 1; // 默认第一页
        $offset = ($page - 1) * $perPage;
        $order = isset($data['offset']) ? $data['offset'] : 'DESC'; // 默认降序排序
        $data['tosort'] = isset($data['tosort']) ? $data['tosort'] : 'time';
        // 根據哪個表排序
        //表分類 時間 time 人氣(瀏覽量)：popularity
        $lRowCount = 0;
        $orderByColumn = null;
        if ($data['tosort'] == 'time') {
            $orderByColumn = 'id';
        } elseif ($data['tosort'] == 'popularity') {
            $orderByColumn = 'PageView';
        }

        //按照时间  按照人气 不过这两点
        $arr = [];
        try {
            if ($data['id']) {
                $condition = ['id' => $data['id']];
            } else if ($data['alias'] && !$data['Answered'] && !$data['Rewardissue']) {
                // 通过别名获取PID
                //获取自己的ID
                $catlist = [];
                $list = $this->model->onfetch('*', 'dex_ification', 1, ['alias' => $data['alias']]);
                array_push($catlist, $list['data']['id']);
                $arrlist = $this->model->onfetch('*', 'dex_ification', 9, []);
                $arrlist = $arrlist['data'];
                $catlist =   array_merge($catlist, $this->Toquill->recursionabs($arrlist, $list['data']['id']));
                // 需要一个或的条件
                $condition = ['pid' => $catlist];
            } else if ($data['userid']) {
                // 用户ID的帖子
                $condition = ['userid' => $data['userid']];
            } else if ($data['collect']) {
                $arrlist = $this->model->onfetch('postid', 'dex_qacollect', 9, ['userid' => $data['collect']])['data'];
                $condition = ['id' => array_column($arrlist, 'postid')];
                // 收藏
            } else if ($data['Answered']) {

                // 已经有回答的
                $catlist = [];
                $list = $this->model->onfetch('*', 'dex_ification', 1, ['alias' => $data['alias']]);
                array_push($catlist, $list['data']['id']);
                $arrlist = $this->model->onfetch('*', 'dex_ification', 9, []);
                $arrlist = $arrlist['data'];
                $catlist =   array_merge($catlist, $this->Toquill->recursionabs($arrlist, $list['data']['id']));

                $arrlist = $this->model->onfetch('PostID', 'dex_answer', 9, [])['data'];
                $condition = ['id' => array_unique(array_column($arrlist, 'PostID')), 'pid' => $catlist];
                if (!$data['alias']) {
                    $condition = ['id' => array_unique(array_column($arrlist, 'PostID'))];
                }
            } else if ($data['Rewardissue']) {
                $catlist = [];
                $list = $this->model->onfetch('*', 'dex_ification', 1, ['alias' => $data['alias']]);
                array_push($catlist, $list['data']['id']);
                $arrlist = $this->model->onfetch('*', 'dex_ification', 9, []);
                $arrlist = $arrlist['data'];
                $catlist =   array_merge($catlist, $this->Toquill->recursionabs($arrlist, $list['data']['id']));
                // 已经有回答的
                $condition = ['Price >' => 0, 'pid' => $catlist];
                if (!$data['alias']) {
                    $condition = ['Price >' => 0];
                }
            } else {
                // 没有条件  现在的问题是 如何计算前两个  现在获取了0-50  50-52
                $condition = [];
            }
            $arr = $this->model->fetchPage('dex_issueslist', $orderByColumn, $offset, $perPage, $order, $condition);
            $lRowCount = $this->model->getTotalRowCount('dex_issueslist', $condition);
            if ($arr['code'] == 200) {
                $newarr = [];
                foreach ($arr['data'] as $key => $value) {
                    $list = $value;
                    $list['Price'] = number_format($list['Price'], 2);
                    $list['user'] =  $this->model->onfetch('*', 'dex_user', 1, ['id' => $list['userid']])['data'];
                    unset($list['user']['password'], $list['user']['balance'], $list['user']['integral']);
                    $cate = $this->model->onfetch('*', 'dex_ification', 1, ['id' => $list['pid']])['data'];
                    $list['pidname'] = $cate['name'];
                    $list['alias'] = $cate['alias'];
                    $list['content'] =  json_decode($list['content'], true);
                    $cate = $this->model->onfetch('*', 'dex_answer', 9, ['PostID' => $list['id'], 'level' => 0])['data'];
                    $list['collect'] = $this->model->onfetch('*', 'dex_qacollect', 9, ['PostID' => $data['id']])['data'];
                    // 通过ID查询是否有回答
                    $list['optimal'] = $this->model->onfetch('optimal', 'dex_answer', 1, ['PostID' => $list['id'], 'optimal' => 1])['data'];
                    $list['reply'] = $cate;
                    array_push($newarr, $list);
                }
                echo json_encode(retur($lRowCount, $newarr));
            } else {
                echo json_encode(retur('查询结果为空', $arr['data'], 404));
            }
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage();
            $errorLine = $th->getLine();
            echo json_encode(retur('程序错误', "错误信息：{$errorMessage}，发生在第 {$errorLine} 行。", 5001));
        }
    }


    // 仅限管理员调用
    public function getuserlit()
    {

        //接收参数  页码  每页记录数  排序参数 倒序还是顺序
        //这样的话  首页  直接获取最近50条好了
        $data = json_decode(file_get_contents('php://input'), true);

        $test = self::testandverify();
        if (isset($test) && $test['administrators'] == 1) {
            // 接收分页参数
            $perPage = isset($data['perPage']) ? $data['perPage'] : 1; // 默认每页记录数为 10
            $page = isset($data['page']) ? $data['page'] : 1; // 默认第一页
            $offset = ($page - 1) * $perPage;
            $order = isset($data['offset']) ? $data['offset'] : 'DESC'; // 默认降序排序
            // 条件
            $condition = $data['condition'];
            // 都肯定是按照ID排序
            $arr = $this->model->fetchPage('dex_user', 'id', $offset, $perPage, $order, $condition);
            if ($arr['code'] == 200) {

                foreach ($arr['data'] as $key => $value) {
                    # code...
                    $arr['data'][$key]['balance'] =  number_format($value['balance'], 3);
                    $arr['data'][$key]['integral'] =  number_format($value['integral']);
                    $arr['data'][$key]['Group'] = $this->model->onfetch('*', 'dex_UserGroup', 1, ['id' => $value['UserGroup']])['data'];
                }
            }
            $lRowCount = $this->model->getTotalRowCount('dex_user', $condition);

            echo json_encode(retur($lRowCount, $arr['data']));
        } else {
            echo json_encode(retur('失败', '非法访问', 3000));
        }
    }

    // 仅限管理员调用
    public function onpage()
    {

        //接收参数  页码  每页记录数  排序参数 倒序还是顺序
        //这样的话  首页  直接获取最近50条好了
        $data = json_decode(file_get_contents('php://input'), true);
        $test = self::testandverify();
        if (isset($test) && $test['administrators'] == 1) {
            // 接收分页参数
            $perPage = isset($data['perPage']) ? $data['perPage'] : 1; // 默认每页记录数为 10
            $page = isset($data['page']) ? $data['page'] : 1; // 默认第一页
            $offset = ($page - 1) * $perPage;
            $order = isset($data['offset']) ? $data['offset'] : 'DESC'; // 默认降序排序
            $from = $data['from'];
            if (!isset($data['tosort'])) {
                $data['tosort'] = 'id';
            }
            // 条件
            $condition = $data['condition'];
            // 都肯定是按照ID排序
            $arr = $this->model->fetchPage($from,  $data['tosort'], $offset, $perPage, $order, $condition);
            $lRowCount = $this->model->getTotalRowCount($from, $condition);

            echo json_encode(retur($lRowCount, $arr['data']));
        } else {
            echo json_encode(retur('失败', '非法访问', 3000));
        }
    }
    // 修改折扣 这里限制了 只有课程管理员和管理员可操作
    public function  onchangediscount()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $test = self::testandverify();
        $adminid = $this->model->onfetch('*', 'dex_CourseTable', 1, ['id' => $data['id']])['data']['admin'];
        if ((isset($test) && $test['administrators'] == 1) || $test['id'] == $adminid) {
            $condition = ['id' => $data['id']];
            unset($data['id']);
            $arr =  $this->model->onchange('dex_CourseTable', $data,  $condition);
            echo json_encode($arr);
        } else {
            echo json_encode(retur('失败', '非法访问', 3000));
        }
    }
    // 修改讲师
    public function onchangelecturer()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $test = self::testandverify();
        if (isset($test) && $test['administrators'] == 1) {
            $arr =  $this->model->onchange('dex_CourseTable', ['lecturer' => $data['lecturer']], ['id' => $data['id']]);
            echo json_encode($arr);
        } else {
            echo json_encode(retur('失败', '非法访问', 3000));
        }
    }
    // 关联课程
    public function Relatedcourses()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $test = self::testandverify();
        if (isset($test) && $test['administrators'] == 1) {
            $arr =  $this->model->onchange('dex_CourseTable', ['Relatedcourses' => $data['lecturer']], ['id' => $data['id']]);
            echo json_encode($arr);
        } else {
            echo json_encode(retur('失败', '非法访问', 3000));
        }
    }
    // 课程列表查询
    public function Courseonpage()
    {

        //接收参数  页码  每页记录数  排序参数 倒序还是顺序
        //这样的话  首页  直接获取最近50条好了
        $data = json_decode(file_get_contents('php://input'), true);

        $test = self::testandverify();
        if (isset($test) && $test['administrators'] == 1) {
            // 接收分页参数
            $perPage = isset($data['perPage']) ? $data['perPage'] : 1; // 默认每页记录数为 10
            $page = isset($data['page']) ? $data['page'] : 1; // 默认第一页
            $offset = ($page - 1) * $perPage;
            $order = isset($data['offset']) ? $data['offset'] : 'DESC'; // 默认降序排序
            $from = 'dex_CourseTable';
            if (!isset($data['tosort'])) {
                $data['tosort'] = 'id';
            }
            // 条件
            $condition = $data['condition'];
            // 都肯定是按照ID排序
            $arr = $this->model->fetchPage($from,  $data['tosort'], $offset, $perPage, $order, $condition);
            if ($arr['code'] == 200) {
                foreach ($arr['data'] as $key => $value) {
                    # code...
                    $cate = $this->model->onfetch('*', 'dex_CourseTypes', 1, ['id' => $arr['data'][$key]['pid']])['data'];
                    $arr['data'][$key]['pidname'] = $cate['name'];
                    $arr['data'][$key]['lecturer'] = json_decode($arr['data'][$key]['lecturer'], true);
                    $arr['data'][$key]['Relatedcourses'] = json_decode($arr['data'][$key]['Relatedcourses'], true);
                    $arr['data'][$key]['alias'] = $cate['alias'];
                    $arr['data'][$key]['user'] =   $this->model->onfetch('*', 'dex_user', 1, ['id' => $arr['data'][$key]['admin']])['data'];
                    unset($arr['data'][$key]['user']['password'],  $arr['data'][$key]['user']['balance'],  $arr['data'][$key]['user']['integral']);
                    $arr['data'][$key]['content'] =  json_decode($arr['data'][$key]['content'], true);
                }
            }
            $lRowCount = $this->model->getTotalRowCount($from, $condition);

            echo json_encode(retur($lRowCount, $arr['data']));
        } else {
            echo json_encode(retur('失败', '非法访问', 3000));
        }
    }
    // 课程列表查询
    public function myCourseonpage()
    {

        //接收参数  页码  每页记录数  排序参数 倒序还是顺序
        //这样的话  首页  直接获取最近50条好了
        $data = json_decode(file_get_contents('php://input'), true);

        $test = self::testandverify();
        // 接收分页参数
        $perPage = isset($data['perPage']) ? $data['perPage'] : 1; // 默认每页记录数为 10
        $page = isset($data['page']) ? $data['page'] : 1; // 默认第一页
        $offset = ($page - 1) * $perPage;
        $order = isset($data['offset']) ? $data['offset'] : 'DESC'; // 默认降序排序
        $from = 'dex_CourseTable';
        if (!isset($data['tosort'])) {
            $data['tosort'] = 'id';
        }
        $list = $this->model->onfetch('*', 'dex_Order', 9, ['Type' => 1, 'user' => $test['id']])['data'];
        $condition = [];
        // echo json_encode(retur(0, $list));

        // exit;
        if (count($list) > 0) {
            $list =  array_column($list, 'ProductID');
            $condition = ['id' => $list];
        } else {
            echo json_encode(retur(0, []));
            exit;
        }

        // 条件
        // 都肯定是按照ID排序
        $arr = $this->model->fetchPage($from, $data['tosort'], $offset, $perPage, $order, $condition);
        // echo json_encode(retur(0, [$from, $data['tosort'], $offset, $perPage, $order, $condition]));
        // exit;
        if ($arr['code'] == 200) {
            foreach ($arr['data'] as $key => $value) {
                # code...
                $cate = $this->model->onfetch('*', 'dex_CourseTypes', 1, ['id' => $arr['data'][$key]['pid']])['data'];
                $arr['data'][$key]['pidname'] = $cate['name'];
                $arr['data'][$key]['lecturer'] = json_decode($arr['data'][$key]['lecturer'], true);
                $arr['data'][$key]['Relatedcourses'] = json_decode($arr['data'][$key]['Relatedcourses'], true);
                $arr['data'][$key]['alias'] = $cate['alias'];
                $arr['data'][$key]['user'] =   $this->model->onfetch('*', 'dex_user', 1, ['id' => $arr['data'][$key]['admin']])['data'];
                unset($arr['data'][$key]['user']['password'],  $arr['data'][$key]['user']['balance'],  $arr['data'][$key]['user']['integral']);
                $arr['data'][$key]['content'] =  json_decode($arr['data'][$key]['content'], true);
            }
        }
        $lRowCount = $this->model->getTotalRowCount($from, $condition);
        echo json_encode(retur($lRowCount, $arr['data']));
    }

    // 获取分类面包屑（层级关系文章）
    public function Obtainclassification()
    {

        $data = json_decode(file_get_contents('php://input'), true);
        // 获取上级ID
        $pid =  $this->model->onfetch('*', 'dex_category', 1, ['alias' => $data])['data'];
        $arr = [];
        array_unshift($arr, $pid);
        $pid = $pid['pid'];
        // 查询上级
        while ($pid != 0) {
            // 继续查询上级
            $list =  $this->model->onfetch('*', 'dex_category', 1, ['id' => $pid])['data'];
            $pid = $list['pid'];
            array_unshift($arr, $list);
        }
        echo json_encode(retur('成功', $arr));
    }

    // 添加修改文章
    public function setarticle()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $test = self::testandverify();
        if (!isset($data['id']) && isset($test) && ($test['administrators'] == 1 || $data['userid'] == $test['id'])) {

            //需要表名
            $from = 'dex_article';
            $data['content'] = $data['content'];
            $arr = $this->model->sqladd($from, $data);
            // 接收到参数发送给增加的
            echo json_encode($arr);
        } else if (isset($data['id']) && isset($test) && ($test['administrators'] == 1 || $data['userid'] == $test['id'])) {
            // 修改

            $id = $data['id'];
            unset($data['id']);
            $arr = $this->model->onchange('dex_article', $data, ['id' => $id]);
            echo json_encode($arr);
        } else {
            echo json_encode(retur('失败', '非法访问', 3000));
        }
    }
    // 添加问题
    public function setquestions()
    {

        $data = json_decode(file_get_contents('php://input'), true);
        $test = self::testandverify();
        // 存在文章ID 是修改  不存在就是添加
        if (!isset($data['id']) && isset($test)) {
            $data['userid'] = $test['id'];

            //需要表名
            //还需要类目名称
            $from = 'dex_issueslist';
            $arr = $this->model->sqladd($from, $data);
            // 接收到参数发送给增加的
            echo json_encode($arr);
        } else if (isset($data['id']) && isset($test) && $test['administrators'] == 1) {
            // 修改  只有管理员可以修改

            $id = $data['id'];
            unset($data['id']);
            $data['content'] = $data['content'];
            $arr = $this->model->onchange('dex_issueslist', $data, ['id' => $id]);
            echo json_encode($arr);
        } else {
            echo json_encode(retur('失败', '非法访问', 3000));
        }
    }

    // 用户注册 需要判断用户是否存在
    public function Registration()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        //需要表名
        $from = 'dex_user';
        //判断是否存在
        $arr = $this->model->onfetch('username', $from, 1, ['username' => $data['username']]);
        // $arr = $this->model->sqladd($from, $data);
        if (!$arr['data']['username']) {
            // 这里特么必须删除注册用户的钱包信息
            //需要判断默认用户组
            $UserGroup = $this->model->onfetch('DefaultUserGroup', 'dex_Settings', 1, [])['data']['DefaultUserGroup'];
            $data['UserGroup'] = $UserGroup;
            unset($data['balance'], $data['integral']);
            $arr = $this->model->sqladd($from, $data);
            $template_path = __DIR__ . '/../mail/Registration.html'; // 替换为模板文件的实际路径
            $template_content = file_get_contents($template_path);
            $template_content = str_replace('{{username}}', $data['username'], $template_content);

            $this->Toquill->mail($data['email'], $data['username'], '欢迎来到DEXC区块链开发者社区', $template_content);
            echo json_encode($arr);
        } else {
            echo json_encode(retur('错误', '用户已存在', 2000));
        }
    }
    // 修改
    public function onchange()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $test = self::testandverify();
        if (isset($test) && $test['administrators'] == 1) {

            //需要表名
            $from = $data['from'];
            // 删除数组中的表名
            unset($data['from']);
            // 获取条件
            $condition = $data['condition'];
            // 删除条件
            unset($data['condition']);
            // 发送表名 需要添加的数组
            $arr = $this->model->onchange($from, $data, $condition);
            // 接收到参数发送给增加的
            echo json_encode($arr);
        } else {
            echo json_encode(retur('失败', $test, 3000));
        }
    }
    // 删除
    public function ondeletedata()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $test = self::testandverify();
        if (isset($test) && $test['administrators'] == 1) {
            //需要表名
            $from = $data['from'];
            // 删除数组中的表名
            unset($data['from']);
            // 发送表名 需要添加的数组
            $arr = $this->model->ondeletedata($from, $data);
            // 接收到参数发送给增加的
            echo json_encode($arr);
        } else {
            echo json_encode(retur('失败', '非法访问', 3000));
        }
    }

    // 做一个增加表的函数
    // 写入
    public function increase()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $test = self::testandverify();
        if (isset($test) && $test['administrators'] == 1) {
            //查詢別名是否存在
            //需要表名
            $from = $data['from'];
            // 删除数组中的表名
            unset($data['from']);
            // 发送表名 需要添加的数组
            $arr = $this->model->sqladd($from, $data);
            // 接收到参数发送给增加的
            echo json_encode($arr);
        } else {
            echo json_encode(retur('失败', '非法访问', 3000));
        }
    }

    // 查询
    // 必须管理管 因为其他用户可以通过这个接口盗取数据库信息
    // 有可能获取用户登陆信息然后作为漏洞
    public function onfetch()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $test = self::testandverify();
        if (isset($test) && $test['administrators'] == 1) {
            //需要表名
            $from = $data['from'];
            // 删除数组中的表名
            unset($data['from']);
            // 发送表名 需要添加的数组
            $result = $data['result'] ? $data['result'] : '*';
            unset($data['result']);
            $types = $data['type'] ? $data['type'] : 1;
            unset($data['type']);
            if (!$data) {
                $data = '';
            }
            $arr = $this->model->onfetch($result, $from, $types, $data);
            // 接收到参数发送给增加的
            echo json_encode($arr);
        }
    }


    // 绑定Bindgithub
    public function Bindgithub()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $user =  self::testandverify();
        $token = false;
        $limt = '';
        if (isset($data['message']) && isset($data['signature'])) {

            $ethAddress = ETHverifyMessage($data['message'], $data['signature']);
            if ($ethAddress) {
                if (strtolower($data['address']) == strtolower($ethAddress)) {
                    $token = strtolower($ethAddress);
                    $limt = 'address';
                }
            }
        } else if ($data['code']) {
            $token = self::getUserInfoFromGitHub($data['code']);
            $limt = 'githubid';
        }
        if ($token) {
            $for = $this->model->onfetch('*', 'dex_user', 1, [$limt => $token])['data'];
            if (!$for) {
                $arr = $this->model->onchange('dex_user', [$limt => $token], ['id' => $user['id']]);
                echo json_encode($arr);
            } else {
                echo json_encode(retur('失败', '请勿绑定相同账号', 900));
            }
        } else {
            echo json_encode(retur('失败', '访问非法', 900));
        }
    }




    // 头像上传
    public function pictureupload()
    {
        // 这里获取传上来的值
        // 验证登录
        $data =  self::testandverify();
        // 判断是否有上传图片
        //    上传文件
        $arr =  $this->Toquill->files('Avatar/');
        if ($arr['code'] == 200) {
            //  存入数据库
            $arr = $this->model->onchange('dex_user', ['Avatar' => $arr['data']], ['id' => $data['id']]);
            if ($arr['code'] == 200) {
                echo json_encode(retur('头像上传成功', '上传成功'));
                exit;
            } else {
                echo json_encode($arr);
                exit;
            }
        } else {
            echo json_encode(retur('失败', $arr, 701));
            exit;
        }
    }

    // 文件上传
    public function files()
    {
        // 这里获取传上来的值

        $arr =  $this->Toquill->files();
        echo json_encode($arr);
    }
    // 视频上传
    public function handleChunkedUpload()
    {
        // 这里获取传上来的值
        try {
            self::testandverify();
            $data = json_decode(file_get_contents('php://input'), true);
            $videoId = '';
            $arr = '';
            if ($data['vidoname']) {
                $arr   =  $this->Toquill->handleChunkedUpload();
                $videoId  = $arr['videoId'];
            }
            $result = '';
            if ($data['condition']) {
                // 修改
                $result  = $this->model->onchange('dex_VideoTable', ['chapter' => $data['chapter'], 'title' => $data['title'], 'UploadID' => $videoId], $data['condition']);
            } else {
                $result = $this->model->sqladd('dex_VideoTable', ['chapter' => $data['chapter'], 'title' => $data['title'], 'UploadID' => $videoId]);
            }
            if ($data['vidoname']) {
                echo json_encode(retur('成功', $arr));
            } else {
                echo json_encode($result);
            }
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', $th->getMessage(), 20000));
        }
    }
    // 视频回调
    function onVideoUploadSuccess()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        ignore_user_abort(true);
        $callbackUrl = 'https://api.dexc.pro/api.php?post=onVideoUploadSuccess';
        $authKey = 'aBcD1234EFGH5678IJKLMN9012OPQRSt';
        $timestamp = $_SERVER['HTTP_X_VOD_TIMESTAMP'];
        $signature = $_SERVER['HTTP_X_VOD_SIGNATURE'];
        $localSignature = md5($callbackUrl . '|' . $timestamp . '|' . $authKey);
        // 验证时间戳是否在合理范围内（例如，不早于当前时间的一定时间段）
        $currentTimestamp = time();
        $validTimestampRange = 60; // 有效时间范围为60秒（根据需求调整）
        if ($localSignature == $signature && ($currentTimestamp - $timestamp) < $validTimestampRange) {
            $duration =  $this->Toquill->getPlayInfo($data['VideoId']);
            $delarr =  $this->model->onfetch('vidoid', 'dex_VideoTable', 9, ['UploadID' => $data['VideoId']])['data'];
            if ($delarr['vidoid']) {
                $this->Toquill->DeleteVideo($delarr['vidoid']);
            }
            $arr = $this->model->onchange('dex_VideoTable', ['duration' => $duration, 'vidoid' => $data['VideoId']], ['UploadID' => $data['VideoId']]);
            if ($arr['code'] == 200) {
                http_response_code(200);
            } else {
                http_response_code(404);
            }
        } else {
            http_response_code(404);
        }


        // 处理视频上传成功的回调事件
    }
    // 删除视频
    public function DeleteVideo()
    {
        //  驗證用戶
        $test = self::testandverify();
        if ($test['administrators'] == 1) {
            $data = json_decode(file_get_contents('php://input'), true);
            $delarr =  $this->model->onfetch('vidoid', 'dex_VideoTable', 9, ['id' => $data])['data'];
            $vidArray = array(); // 创建一个新的数组来存储 "vid" 的值
            foreach ($delarr as $item) {
                if (isset($item['vidoid'])) {
                    $vidArray[] = $item['vidoid']; // 提取 "vid" 的值并添加到新数组中
                }
            }
            $duration = $this->Toquill->DeleteVideo($vidArray);
            $arr = $this->model->ondeletedata('dex_VideoTable', ['id' => $data]);
            echo json_encode($arr);
        }
    }
    // 删除课节
    public function Deletection()
    {
        //  驗證用戶
        $test = self::testandverify();
        if ($test['administrators'] == 1) {
            $data = json_decode(file_get_contents('php://input'), true);
            // 判断他有没有上级
            $delarr =  $this->model->onfetch('*', 'dex_VideoTable', 9, ['chapter' => $data])['data'];
            if (count($delarr) == 0) {
                $arr = $this->model->ondeletedata('dex_ChapterTable', ['id' => $data]);
                echo json_encode($arr);
            } else {
                echo json_encode(retur('失败', '当前课节已有内容，请先删除视频', 404));
            }
        }
    }
    // 删除课程
    public function DeleteCourseTable()
    {
        //  驗證用戶
        $test = self::testandverify();
        if ($test['administrators'] == 1) {
            $data = json_decode(file_get_contents('php://input'), true);
            // 判断他有没有上级
            $delarr =  $this->model->onfetch('*', 'dex_ChapterTable', 9, ['course' => $data])['data'];
            if (count($delarr) == 0) {
                // 这里删除图片
                $courseData = $this->model->onfetch('*', 'dex_CourseTable', 1, ['id' => $data[0]])['data'];
                foreach (['Displayimages', 'thumbnail'] as $field) {
                    $url = $courseData[$field];
                    $urlParts = parse_url($url);
                    $urlParts = ltrim($urlParts['path'], '/');
                    $this->Toquill->deleteOSSFile($urlParts);
                }

                $arr = $this->model->ondeletedata('dex_CourseTable', ['id' => $data]);
                echo json_encode($arr);
            } else {
                echo json_encode(retur('失败', '当前课程已有课节，请先删除课节', 404));
            }
        }
    }
    // 采集
    public function getmifengcha()
    {
        $this->Toquill->getmifengcha();
    }
    // 课程收藏
    public function Savecourse()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $test = self::testandverify();
        $WHERE = ['PostID' => $data, 'userid' => $test['id']];
        $array =  $this->model->onfetch('*', 'dex_Savecourse', 1, $WHERE)['data'];
        $result = retur('失败', false, 8000);
        if ($array) {
            $result = $this->model->ondeletedata('dex_Savecourse', $WHERE);
        } else {
            $result = $this->model->sqladd('dex_Savecourse', $WHERE);
        }
        echo json_encode($result);
    }
    // 课程收藏
    public function getSavecourse()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $test = self::testandverify();
        $WHERE = ['PostID' => $data, 'userid' => $test['id']];
        $array =  $this->model->onfetch('*', 'dex_Savecourse', 1, $WHERE)['data'];
        if ($array) {
            echo json_encode(retur('成功', true));
        } else {
            echo json_encode(retur('未收藏', false, 400));
        }
    }
    // 获取文章关键词

    //帖子点赞收藏添加与删除
    public function LikeCollection()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        //  驗證用戶
        $test = self::testandverify();
        if (!$test) {
            echo json_encode(retur('失败', '用戶未登錄', 9000));
            return;
        }
        $Table = '';
        $WHERE = ['PostID' => $data['PostID'], 'userid' => $test['id']];

        if ($data['type'] == 1) {
            //  判断表
            $Table = 'dex_Like';
        } else {
            // 收藏
            $Table = 'dex_collect';
        }
        $array =  $this->model->onfetch('*', $Table, 1, $WHERE);
        $array = $array['data'];

        $result = '';
        if ($array) {
            // 存在刪除
            $result = $this->model->ondeletedata($Table, $WHERE);
        } else {
            // 添加
            $result = $this->model->sqladd($Table, $WHERE);
        }
        if ($result) {
            echo json_encode($result);
        } else {
            echo json_encode($result);
        }
    }


    //帖子点赞收藏添加与删除
    public function  followinterest()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        //  驗證用戶
        $test = self::testandverify();
        if (!$test) {
            echo json_encode(retur('失败', '用戶未登錄', 404));
            exit;
        }
        $Table = 'dex_follow';
        $WHERE = ['toid' => $data['toid'], 'fromid' => $test['id']];

        $array =  $this->model->onfetch('*', $Table, 1, $WHERE);
        // 是否查询关注
        if (isset($data['fans'])) {
            if ($array['data']) {
                echo json_encode(retur('成功', true));
                exit;
            } else {
                echo json_encode(retur('成功', false));
                exit;
            }
        }

        $array = $array['data'];

        $result = '';
        if ($array) {
            // 存在刪除
            $result = $this->model->ondeletedata($Table, $WHERE);
        } else {
            if ($data['toid'] == $test['id']) {
                echo json_encode(retur('失败', '自己不能关注自己', 1001));
                exit;
            }
            // 添加
            $result = $this->model->sqladd($Table, $WHERE);
        }
        if ($result) {
            echo json_encode($result);
        } else {
            echo json_encode($result);
        }
    }

    // 文章回复
    public function PostReply()
    {

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            self::testandverify();
            // 开始添加
            // 发送表名 需要添加的数组
            if ($data['CommentID']) {
                $data['level'] = 1;
            };
            $data['content'] = $data['content'];
            $arr = $this->model->sqladd('dex_reply', $data);
            echo json_encode($arr);
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', $th, 501));
        }
    }
    // 问题回复
    public function Postanswer()
    {

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            self::testandverify();

            // 开始添加
            // 发送表名 需要添加的数组
            if ($data['CommentID']) {
                $data['level'] = 1;
            };
            $data['content'] = $data['content'];
            $arr = $this->model->sqladd('dex_answer', $data);
            echo json_encode($arr);
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', $th, 501));
        }
    }
    // 获取文章回复
    public function getPostReply()
    {

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $arr = $this->model->onfetch('*', 'dex_reply', 9, $data)['data'];

            // 将数据转换为以 id 为键的关联数组，方便后续查找
            $dataMap = [];
            foreach ($arr as $value) {
                $dataMap[$value['id']] = $value;
            }
            // 查询最终上级

            // 这里添加和修改相关函数
            foreach ($arr as $key => $value) {
                $arr[$key]['content'] = json_decode($arr[$key]['content']);
                $arr[$key]['username'] = $this->model->onfetch('*', 'dex_user', 1, ['id' => $value['userid']])['data'];
                unset($arr[$key]['username']['id'], $arr[$key]['username']['password'],  $arr[$key]['username']['balance'],  $arr[$key]['username']['integral']);
                if ($value['CommentID']) {
                    // 存在上级的 $this->model->onfetch('userid', 'dex_reply', 1, ['id' => $value['CommentID']])['data']['userid'];
                    $arr[$key]['superiorname'] = $this->model->onfetch('*', 'dex_user', 1, ['id' => $this->model->onfetch('userid', 'dex_reply', 1, ['id' => $value['CommentID']])['data']['userid']])['data'];
                    unset($arr[$key]['superiorname']['id'], $arr[$key]['superiorname']['password'], $arr[$key]['superiorname']['balance'],  $arr[$key]['superiorname']['integral']);
                    $arr[$key]['pid'] = $this->Toquill->reprecursion($value['CommentID'], $dataMap);
                } else {
                    $arr[$key]['pid'] = 0;
                }
            }
            $nearr = $this->Toquill->treearray(array_column($arr, null, 'id'));
            echo json_encode(retur('成功', $nearr));
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', $th, 501));
        }
    }
    // 获取问答回复
    public function getanswer()
    {

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $arr = $this->model->onfetch('*', 'dex_answer', 9, $data)['data'];
            // 将数据转换为以 id 为键的关联数组，方便后续查找
            $dataMap = [];
            foreach ($arr as $value) {
                $dataMap[$value['id']] = $value;
            }
            // 这里添加和修改相关函数
            foreach ($arr as $key => $value) {
                $arr[$key]['content'] = json_decode($arr[$key]['content']);
                $arr[$key]['username'] = $this->model->onfetch('*', 'dex_user', 1, ['id' => $value['userid']])['data'];
                unset($arr[$key]['username']['id'], $arr[$key]['username']['password'],  $arr[$key]['username']['balance'],  $arr[$key]['username']['integral']);
                if ($value['CommentID']) {
                    $arr[$key]['superiorname'] = $this->model->onfetch('*', 'dex_user', 1, ['id' => $this->model->onfetch('userid', 'dex_answer', 1, ['id' => $value['CommentID']])['data']['userid']])['data'];
                    unset($arr[$key]['superiorname']['id'], $arr[$key]['superiorname']['password'], $arr[$key]['superiorname']['balance'],  $arr[$key]['superiorname']['integral']);
                    $arr[$key]['pid'] = $this->Toquill->reprecursion($value['CommentID'], $dataMap);
                } else {
                    $arr[$key]['pid'] = 0;
                }
            }
            $nearr = $this->Toquill->treearray(array_column($arr, null, 'id'));
            echo json_encode(retur('成功', $nearr));
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', $th, 501));
        }
    }
    public function adopt()
    {

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $postuserid = self::testandverify();
            $postuserid = $postuserid['id'];

            // 使用问答获取用户id
            $userid =  $this->model->onfetch('userid', 'dex_issueslist', 1, ['id' => $data['articid']])['data']['userid'];
            //  利用回复ID查询问答ID
            $PostID =  $this->model->onfetch('PostID', 'dex_answer', 1, ['id' => $data['CommentID']])['data']['PostID'];
            // 后端验证问答ID 的作者ID 是不是等于用户ID  且回复ID的回复的是当前问答ID  如果是 就将回复ID设为最佳
            // 还有当前不存在最优答案 通过当前
            $optimal =  $this->model->onfetch('*', 'dex_answer', 1, ['PostID' => $data['articid'], 'optimal' => 1])['data'];

            if ($postuserid != $userid && $PostID == $data['articid'] && !$optimal) {
                //将这条答案设置为最优
                $arr = $this->model->onchange('dex_answer', ['optimal' => 1], ['id' => $data['CommentID']]);
                echo json_encode($arr);
            } else if ($postuserid == $userid) {
                echo json_encode(retur('失败', '不能设置自己为最佳答案', 601));
            } else {
                echo json_encode(retur('失败', '非法访问', 601));
            }
            //code...
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', $th, 601));
        }
    }
    // 问题收藏
    public function qaection()
    {

        $data = json_decode(file_get_contents('php://input'), true);
        //  驗證用戶
        $test = self::testandverify();

        $Table = '';
        $WHERE = ['PostID' => $data['PostID'], 'userid' => $test['id']];
        // 收藏
        $Table = 'dex_qacollect';

        $array =  $this->model->onfetch('*', $Table, 1, $WHERE);
        $array = $array['data'];
        $result = '';
        if ($array) {
            // 存在刪除
            $result = $this->model->ondeletedata($Table, $WHERE);
        } else {
            // 添加
            $result = $this->model->sqladd($Table, $WHERE);
        }
        echo json_encode($result);
    }
    // 修改个人资料 且防止余额积分被篡改
    public function Datamodification()
    {

        $data = json_decode(file_get_contents('php://input'), true);
        //  驗證用戶
        try {
            $test = self::testandverify();
            unset($data['integral']);
            unset($data['balance']);
            foreach ($data as $key => $value) {
                # code...
                if ($data[$key]) {
                    $data[$key] = addslashes($value);
                }
            }
            $arr =   $this->model->onchange('dex_user', $data, ['id' => $test['id']]);
            echo json_encode($arr);
        } catch (\Throwable $th) {
            //throw $th;
            $errorMessage = $th->getMessage();
            $errorLine = $th->getLine();
            echo json_encode(retur('失败', "错误信息：{$errorMessage}，发生在第 {$errorLine} 行。", 601));
        }
    }
    // 查询用户帖子数量 问答数量  粉丝数量 和用户信息
    public  function userinformation()
    {
        // 接收一个用户ID
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            // 查询用户的帖子数量
            $posts = $this->model->getTotalRowCount('dex_article', ['userid' => $data]);
            // 问答数量
            $qanum = $this->model->getTotalRowCount('dex_issueslist', ['userid' => $data]);
            // 粉丝数量
            $fans = $this->model->getTotalRowCount('dex_follow', ['toid' => $data]);
            // 查询用户信息
            $arr = $this->model->onfetch('*', 'dex_user', 1, ['id' => $data]);
            $arr = $arr['data'];
            unset($arr['password']);
            if ($arr) {
                $arr['posts'] = $posts ? $posts : 0;
                $arr['qanum'] = $qanum ? $qanum : 0;
                $arr['fans'] = $fans ? $fans : 0;
                $arr['balance'] = floor(strval(($arr['balance'] / 1) * 10000)) / 10000;
                $arr['integral'] = floor(strval(($arr['integral'] / 1) * 10000)) / 10000;
                $array =  $this->model->onfetch('*', 'dex_UserGroup', 1, ['id' => $arr['UserGroup']]);
                $array = $array['data'];
                unset($array['id']);
                unset($array['colour']);
                echo json_encode(retur('成功', array_merge($arr, $array)));
            } else {
                echo json_encode(retur('失败', false, 9000));
            }
        } catch (\Throwable $th) {
            //throw $th;
            $errorMessage = $th->getMessage();
            $errorLine = $th->getLine();
            echo json_encode(retur('失败', "错误信息：{$errorMessage}，发生在第 {$errorLine} 行。", 601));
        }
    }
    // 后台修改添加用户
    public function AddModifyUser()
    {

        $test = self::testandverify();
        if (isset($test) && $test['administrators'] == 1) {
            $data = json_decode($_POST['info'], true);
            $from = 'dex_user';
            if (isset($data['condition'])) {
                // 修改需要判断什么呢
                if ($_FILES) {
                    // 存在图片
                    $arr =  $this->Toquill->files('Avatar/');
                    if ($arr['code'] == 200) {
                        $data['Avatar'] = $arr['data'];
                    }
                }
                $condition = $data['condition'];
                unset($data['condition']);
                $arr = $this->model->onchange($from, $data, $condition);
                echo json_encode($arr);
            } else {
                // 添加
                // 需要判断用户是否存在

                //需要表名
                //判断是否存在
                $arr = $this->model->onfetch('username', $from, 1, ['username' => $data['username']])['data'];
                if (!$arr) {
                    // 可以注册
                    // 判断是否存在图片
                    if ($_FILES) {
                        // 存在图片
                        $arr =  $this->Toquill->files('Avatar/');
                        if ($arr['code'] == 200) {


                            $data['Avatar'] = $arr['data'];
                        }
                    }
                    //    这里添加用户

                    $arr = $this->model->sqladd($from, $data);
                    echo json_encode($arr);
                } else {
                    // 用户已存在
                    echo json_encode(retur('失败', '用户已存在', 1001));
                }
            }
        } else {
            echo json_encode(retur('失败', '非法访问', 3000));
        }
    }

    // 后台修改添加广告
    public function AddModifyads()
    {

        $test = self::testandverify();

        try {
            if (isset($test) && $test['administrators'] == 1) {
                $data = json_decode($_POST['info'], true);
                $from = 'dex_ads';

                if ($_FILES['file']) {
                    // 存在图片
                    $arr =  $this->Toquill->files('ads/');
                    if ($arr['code'] == 200) {
                        $data['img'] = $arr['data'];
                    }
                }
                if (isset($data['condition'])) {
                    // 修改需要判断什么呢

                    $condition = $data['condition'];
                    unset($data['condition']);
                    $arr = $this->model->onchange($from, $data, $condition);
                    echo json_encode($arr);
                } else {
                    // 添加
                    // 需要判断用户是否存在
                    $arr = $this->model->sqladd($from, $data);
                    echo json_encode($arr);
                }
            } else {
                echo json_encode(retur('失败', '非法访问', 3000));
            }
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage();
            $errorLine = $th->getLine();
            echo json_encode(retur('失败', "错误信息：{$errorMessage}，发生在第 {$errorLine} 行。", 601));
        }
    }
    // 修改网站设置
    public function  WebsiteSettings()
    {
        $test = self::testandverify();
        if (isset($test) && $test['administrators'] == 1) {
            $data = json_decode($_POST['info'], true);
            $from = 'dex_Settings';
            $condition = ['id' => '1'];
            if ($_FILES['logo']) {
                $arr =  $this->Toquill->files('Settings/', $_FILES['logo']);
                // 这里要删除LOGO
                if ($arr['code'] == 200) {
                    $url = $this->model->onfetch('logo', $from, 1, $condition)['data']['logo'];
                    $urlParts = parse_url($url);
                    $urlParts = ltrim($urlParts['path'], '/');
                    $this->Toquill->deleteOSSFile($urlParts);
                    $data['logo'] = $arr['data'];
                }
            }

            if ($_FILES['WeChat']) {
                $arr =  $this->Toquill->files('Settings/', $_FILES['WeChat']);
                if ($arr['code'] == 200) {
                    $url = $this->model->onfetch('WeChat', $from, 1, $condition)['data']['WeChat'];
                    $urlParts = parse_url($url);
                    $urlParts = ltrim($urlParts['path'], '/');
                    $this->Toquill->deleteOSSFile($urlParts);
                    $data['WeChat'] = $arr['data'];
                }
            }
            $arr = $this->model->onchange($from, $data, $condition);
            echo json_encode($arr);
        } else {
            echo json_encode(retur('失败', '非法访问', 3000));
        }
    }
    public function  EditCourse()
    {
        $test = self::testandverify();
        $data = json_decode($_POST['info'], true);
        $from = 'dex_CourseTable';
        if (isset($data['condition'])) {
            // 修改
            $condition = $data['condition'];
            unset($data['condition']);
            $adminid = $this->model->onfetch('*', 'dex_CourseTable', 1, $condition)['data']['admin'];
            if ((isset($test) && $test['administrators'] == 1) || $test['id'] == $adminid) {
                if ($_FILES['thumbnail']) {
                    $arr =  $this->Toquill->files('Courses/', $_FILES['thumbnail']);
                    // 这里要删除LOGO
                    if ($arr['code'] == 200) {
                        $url = $this->model->onfetch('thumbnail', $from, 1, $condition)['data']['thumbnail'];
                        $urlParts = parse_url($url);
                        $urlParts = ltrim($urlParts['path'], '/');
                        $this->Toquill->deleteOSSFile($urlParts);
                        $data['thumbnail'] = $arr['data'];
                    }
                }
                if ($_FILES['Displayimages']) {
                    $arr =  $this->Toquill->files('Courses/', $_FILES['Displayimages']);
                    if ($arr['code'] == 200) {
                        $url = $this->model->onfetch('Displayimages', $from, 1, $condition)['data']['Displayimages'];
                        $urlParts = parse_url($url);
                        $urlParts = ltrim($urlParts['path'], '/');
                        $this->Toquill->deleteOSSFile($urlParts);
                        $data['Displayimages'] = $arr['data'];
                    }
                }
                $arr = $this->model->onchange($from, $data, $condition);
                echo json_encode($arr);
            }
        } else {
            // 添加
            if ((isset($test) && $test['administrators'] == 1) || $test['course'] == '1') {
                if ($_FILES['thumbnail']) {
                    $arr =  $this->Toquill->files('Courses/', $_FILES['thumbnail']);
                    // 这里要删除LOGO
                    if ($arr['code'] == 200) {
                        $data['thumbnail'] = $arr['data'];
                    }
                }
                if ($_FILES['Displayimages']) {
                    $arr =  $this->Toquill->files('Courses/', $_FILES['Displayimages']);
                    // 这里要删除LOGO
                    if ($arr['code'] == 200) {
                        $data['Displayimages'] = $arr['data'];
                    }
                }
                $arr = $this->model->sqladd($from, $data);
                echo json_encode($arr);
            }
        }
    }
    // 课程内容 最终直接返回用户是否购买
    public function Coursecontent()
    {
        $this->Toquill->Coursecontent();
    }
    // 获取播放地址
    public function  initVodClient()
    {
        // 这里要判断是否返回视频播放地址
        // 如果不是试看视频  且没有购买  那么不要返回播放地址
        $videoId = json_decode(file_get_contents('php://input'), true);
        $test = self::testandverify();
        $chapter = $this->model->onfetch('*', 'dex_VideoTable', 1, ['vidoid' => $videoId])['data']['chapter'];
        $course = $this->model->onfetch('*', 'dex_ChapterTable', 1, ['id' => $chapter])['data']['course'];
        $Purchased =  $this->model->onfetch('*', 'dex_Order', 1, ['type' => 1, 'ProductID' => $course, 'Status' => 1, 'user' => $test['id']])['data'];
        if ($Purchased) {
            $this->Toquill->initVodClient();
        } else {
            echo json_encode(retur('失败', '课程不存在或者未购买', 909));
        }
    }
    // 获取课件下载地址
    public function  Coursewaredownloadaddress()
    {
        // 这里要判断是否返回视频播放地址
        // 如果不是试看视频  且没有购买  那么不要返回播放地址
        $path = json_decode(file_get_contents('php://input'), true);
        $test = self::testandverify();
        $chapter = $this->model->onfetch('*', 'dexc_courseware', 1, ['path' => $path])['data']['chapter'];
        $course = $this->model->onfetch('*', 'dex_ChapterTable', 1, ['id' => $chapter])['data']['course'];
        $Purchased =  $this->model->onfetch('*', 'dex_Order', 1, ['type' => 1, 'ProductID' => $course, 'Status' => 1, 'user' => $test['id']])['data'];

        if ($Purchased) {
            $xpath =   $this->Toquill->Getfileaccessaddress($path);
            if ($xpath) {
                echo json_encode(retur('成功', $xpath));
            } else {
                echo json_encode(retur('失败', '文件非法', 909));
            }
        } else {
            echo json_encode(retur('失败', '课件不存在或者未购买', 909));
        }
    }
    // 验证课程是否购买
    public function  Verifycoursepurchased()
    {
        $test = self::testandverify();
        $course = json_decode(file_get_contents('php://input'), true);
        $Purchased =  $this->model->onfetch('*', 'dex_Order', 1, ['type' => 1, 'ProductID' => $course, 'Status' => 1, 'user' => $test['id']])['data'];
        if ($Purchased) {
            echo json_encode(retur('成功', true));
        } else {
            echo json_encode(retur('失败', '无结果', 909));
        }
    }


    public function getaddress()
    {

        $this->Toquill->getTransaction();
        $this->Toquill->setsend();

        $this->Toquill->getaddress();
    }
    // 获取钱包地址
    public function GetWalletAddress()
    {
        $this->Toquill->GetWalletAddress();
    }
    //充值监控
    public function RechargeMonitoring()
    {
        $this->Toquill->RechargeMonitoring();
    }
    // 归集
    public function Imputation()
    {
        $this->Toquill->Imputation();
    }
    // 获取上传凭证
    public function Fileuploadauthorization()
    {
        $this->Toquill->Fileuploadauthorization();
    }
    // 获取未领取的优惠券
    public function  Unclaimedvouchers()
    {
        $this->Toquill->Unclaimedvouchers();
    }
    // 领取优惠券
    public function setreceive()
    {
        $this->Toquill->setreceive();
    }

    // 获取课件上传凭证
    public function Coursewareuploadvoucher()
    {
        // 要验证当前用户是否管理员
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $test = self::testandverify();
            $adminid = $this->model->onfetch('*', 'dex_CourseTable', 1, ['id' => $data['id']])['data']['admin'];
            if ((isset($test) && $test['administrators'] == 1) || $test['id'] == $adminid) {
                $prefix = 'courseware_';
                $more_entropy = false; // 启用更多的熵（增加唯一性）
                $testpath = 'courseware/' . $test['id'] . '/';
                $espath = $testpath . uniqid($prefix, $more_entropy) . $data['suffix'];
                $arr =  $this->Toquill->Fileuploadauthorization($espath, 'dexccourseware');
                if ($arr) {
                    $arr->region = 'cn-beijing';
                    $arr->bucket = 'dexccourseware';
                    $arr->endpoint = 'https://oss-cn-beijing.aliyuncs.com';
                    $arr->path = $espath;
                    echo json_encode(retur('成功', $arr));
                } else {
                    echo json_encode(retur('失败', $arr, 404));
                }
            } else {
                echo json_encode(retur('失败', '没有权限', 404));
            }
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', $th, 3000));
        }
        // 这里是需要验证的 验证是否登陆 验证是否管理员 或者是否课程管理员

    }
    public function Coursewaretable()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $test = self::testandverify();
            $adminid = $this->model->onfetch('*', 'dex_CourseTable', 1, ['id' => $data['id']])['data']['admin'];
            if ((isset($data['chapter']) && isset($data['path']) && isset($test) && $test['administrators'] == 1) || $test['id'] == $adminid) {
                // 添加表
                unset($data['id']);
                $data['userid'] = $test['id'];
                $arr = $this->model->sqladd('dexc_courseware', $data);
                echo json_encode($arr);
            } else {
                echo json_encode(retur('失败', '没有权限', 404));
            }
        } catch (\Throwable $th) {
            echo json_encode(retur('失败', $th, 3000));
        }
    }
    // 前端获取课程分页
    public function Coursepagination()
    {
        //接收参数  页码  每页记录数  排序参数 倒序还是顺序
        //这样的话  首页  直接获取最近50条好了
        $data = json_decode(file_get_contents('php://input'), true);
        // 接收分页参数
        // 设置默认值
        $perPage = isset($data['perPage']) ? $data['perPage'] : 10; // 默认每页记录数为 10
        $page = isset($data['page']) ? $data['page'] : 1; // 默认第一页
        $offset = ($page - 1) * $perPage;
        $order = isset($data['offset']) ? $data['offset'] : 'DESC'; // 默认降序排序
        $thumbnailCondition = isset($data['thumbnail']) ? $data['thumbnail'] : '';
        $data['tosort'] = isset($data['tosort']) ? $data['tosort'] : 'time';
        // 根據哪個表排序
        //表分類 時間 time 人氣(瀏覽量)：popularity
        $lRowCount = 0;
        $orderByColumn = null;
        if ($data['tosort'] == 'time') {
            $orderByColumn = 'id';
        } elseif ($data['tosort'] == 'popularity') {
            $orderByColumn = 'PageView';
        }
        //按照时间  按照人气 不过这两点
        $arr = [];
        try {
            if ($data['alias']) {
                $catlist = [];
                $list = $this->model->onfetch('*', 'dex_CourseTypes', 1, ['alias' => $data['alias']]);
                array_push($catlist, $list['data']['id']);
                $arrlist = $this->model->onfetch('*', 'dex_CourseTypes', 9);
                $arrlist = $arrlist['data'];
                $catlist =  array_merge($catlist, $this->Toquill->recursionabs($arrlist, $list['data']['id']));
                // 需要一个或的条件
                $condition = ['pid' => $catlist];
            } else {
                $condition = [];
            }
            // 判断免费还是收费
            if (isset($data['Price'])) {
                //价格参数存在  两个条件  要么大于0  要么=0
                if ($data['Price'] > 0) {
                    $condition['Price >'] = 0;
                } else {
                    $condition['Price'] = 0;
                }
            }
            if (isset($data['Stickie'])) {
                $condition['Stickie'] = $data['Stickie'];
            }
            $arr = $this->model->fetchPage('dex_CourseTable', $orderByColumn, $offset, $perPage, $order, $condition, $thumbnailCondition);
            // echo json_encode(retur('未查询到数据', $arr, 404));
            // exit;
            $lRowCount = $this->model->getTotalRowCount('dex_CourseTable', $condition);
            if ($arr['code'] == 200) {
                $newarr = [];
                foreach ($arr['data'] as $key => $value) {
                    $list = $value;
                    $list['Price'] = number_format($list['Price']);
                    // 获取作者信息
                    $list['user'] =  $this->model->onfetch('*', 'dex_user', 1, ['id' => $list['admin']])['data'];
                    unset($list['user']['password'], $list['user']['balance'], $list['user']['integral']);
                    $cate = $this->model->onfetch('*', 'dex_CourseTypes', 1, ['id' => $list['pid']])['data'];
                    $list['pidname'] = $cate['name'];
                    $list['alias'] = $cate['alias'];
                    $list['content'] = json_decode($list['content'], true);
                    // 判断是否在优惠期间
                    $list['saleperiod'] = false;
                    if ($list['Discountstart'] && $list['DiscountEnd']) {
                        //促销是否有效  生成时间戳
                        $milliseconds = round(microtime(true) * 1000);
                        if ($milliseconds >= $list['Discountstart'] && $milliseconds <= $list['DiscountEnd']) {
                            // 在促销期
                            $list['saleperiod'] = true;
                        }
                    }
                    // 喜欢和收藏  暂时没有
                    // $list['Like'] = $this->model->onfetch('*', 'dex_Like', 9, ['PostID' => $list['id']])['data'];
                    // $list['collect'] = $this->model->onfetch('*', 'dex_collect', 9, ['PostID' => $list['id']])['data'];
                    array_push($newarr, $list);
                }
                echo json_encode(retur($lRowCount, $newarr));
            } else {
                echo json_encode(retur('未查询到数据', $arr['data'], 404));
            }
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage();
            $errorLine = $th->getLine();
            echo json_encode(retur('程序错误', "错误信息：{$errorMessage}，发生在第 {$errorLine} 行。", 5001));
        }
    }
    public function Courselevel()
    {


        $data = json_decode(file_get_contents('php://input'), true);

        $parameter = [];
        $from = 'dex_CourseTypes';

        // 获取当前的分类名

        $pid =  $this->model->onfetch('*', $from, 1, ['alias' => $data])['data'];
        $arr = [];
        array_unshift($arr, $pid);
        $pid = $pid['pid'];
        //查询上级分类名称(这里是循环的)
        while ($pid != 0) {
            // 继续查询上级
            $list =  $this->model->onfetch('*', $from, 1, ['id' => $pid])['data'];
            $pid = $list['pid'];
            array_unshift($arr, $list);
        }
        $parameter['nav'] = $arr;
        // 获取分类  这里可能什么都没有  返回空
        $condition = ['pid' => 0];
        $catlist = [];
        if ($data != '') {
            $condition = ['alias' => $data];
            $list = $this->model->onfetch('*', $from, 1, $condition);
            array_push($catlist, $list['data']);
            $arrlist = $this->model->onfetch('*', $from, 9, []);
            $arrlist = $arrlist['data'];
            $net = $this->Toquill->recursions($arrlist, $list['data']['id']);
            if (empty(!$net)) {
                array_push($catlist, $net);
            }
            $parameter['Classification'] = $catlist;
        } else {
            // 获取所有顶级分类
            $arrlist = $this->model->onfetch('*', $from, 9, $condition);
            $parameter['Classification'] = $arrlist['data'];
        }
        echo json_encode(retur('成功', $parameter));
    }
    // 创建订单
    public function CreateOrder()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $test = self::testandverify();
        // $test['id']
        try {
            $OrderNo = [];
            foreach ($data as  $value) {
                $from = '';
                if ($value['type'] == 1) {
                    // 1   课程
                    $from = 'dex_CourseTable';
                } elseif ($value['type'] == 2) {
                    // 2文章
                    $from = 'dex_article';
                } elseif ($value['type'] == 3) {
                    // 问答
                    $from = 'dex_issueslist';
                } else {
                    // 错误分类
                    continue;
                }

                $arrlist = $this->model->onfetch('*', $from, 1, ['id' => $value['id']]);
                if ($arrlist['code'] == 200) {
                    # code...
                    //  是否通过商品类型 商品ID 和用户ID  支付状态 0 四个条件判断是否存在未支付的订单

                    $huiar = $this->model->onfetch('*', 'dex_Order', 1, ['Type' => $value['type'], 'ProductID' => $value['id'], 'user' => $test['id'],    'Status' => 0])['data'];
                    if ($huiar['name'] && $huiar['OrderNo']) {
                        array_push($OrderNo, ['title' => $huiar['name'], 'OrderNo' => $huiar['OrderNo']]);
                    } else {
                        // 获取当前日期，例如：年月日时分秒
                        $orderNumber = '';
                        $currentDate = date("YmdHis");
                        $counter = 0;
                        while ($counter < 1) {
                            // 生成随机数，可以使用rand()函数
                            $randomNumber = rand(10000, 99999);
                            // 组合日期和随机数来生成订单号
                            $orderNumber = $currentDate . $randomNumber;
                            // 这里的代码将执行一次
                            $num = $this->model->onfetch('*', 'dex_Order', 1, ['OrderNo' => $orderNumber])['data'];
                            if (!$num['dex_Order']) {
                                $counter++;
                            }
                        }
                        $Orderdetails = ['Price' => $arrlist['data']['Price']];
                        $arr = $this->model->sqladd('dex_Order', ['Orderdetails' => $Orderdetails, 'name' => $arrlist['data']['title'], 'OrderNo' => $orderNumber, 'Type' => $value['type'], 'ProductID' => $value['id'], 'user' => $test['id']]);
                        if ($arr['code'] == 200) {
                            array_push($OrderNo, ['title' => $arrlist['data']['title'], 'OrderNo' => $orderNumber]);
                        }
                    }
                }
            }
            echo json_encode(retur('成功', $OrderNo));
        } catch (\Throwable $th) {
            $errorMessage = $th->getMessage();
            $errorLine = $th->getLine();
            echo json_encode(retur('程序错误', "错误信息：{$errorMessage}，发生在第 {$errorLine} 行。", 5001));
        }
    }
    // 订单计算这里只需要计算订单本身的优惠
    // 使用这个方法  主要是避免订单活动价格已经失效
    public function Calculateorders()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $test = self::testandverify();
        try {
            if ($data) {
                foreach ($data as $key => $value) {
                    // 数量
                    $data[$key]['quantity'] = 1;

                    // 查询订单信息
                    $OrderNo = $this->model->onfetch('*', 'dex_Order', 1, ['OrderNo' => $value['OrderNo']])['data'];
                    $data[$key]['title'] = $OrderNo['name'];;
                    // 获取优惠券列表
                    $coupon = array_merge($this->model->onfetch('*', 'dex_coupons', 9, ['Category' => '0'])['data'],  $this->model->onfetch('*', 'dex_coupons', 9, ['Category' => $OrderNo['Type'], 'ProductID' => ['0', $OrderNo['ProductID']]])['data']);
                    $milliseconds = round(microtime(true) * 1000);
                    foreach ($coupon as $keys => $value) {
                        // 查询有没有优惠券
                        if (($milliseconds >= $value['start_time'] || $value['start_time'] == 0) && ($milliseconds <= $value['end_time'] || $value['end_time'] == 0)) {
                            // 在促销期
                            $couponskey =  $this->model->onfetch('*', 'dex_couponskey', 1, ['coupid' => $value['id'], 'userid' => $test['id'], 'state' => 1])['data'];
                            if ($couponskey) {
                                // 存在优惠券
                                $coupon[$keys]['couponskey'] = $couponskey['coupkey'];
                            } else {
                                unset($coupon[$keys]);
                            }
                        } else {
                            unset($coupon[$keys]);
                        }
                    }
                    $data[$key]['couponskey'] = $coupon;
                    // 优惠券获取结束
                    if ($OrderNo['Type'] == 1) {
                        $data[$key]['type'] = $OrderNo['Type'];
                        $data[$key]['ProductID'] = $OrderNo['ProductID'];
                        // 查询商品
                        $Product = $this->model->onfetch('*', 'dex_CourseTable', 1, ['id' => $OrderNo['ProductID']])['data'];
                        if ($Product) {
                            $data[$key]['Price'] =  $Product['Price'];
                            if ($Product['Discountstart'] && $Product['DiscountEnd']) {
                                //促销是否有效  生成时间戳
                                $milliseconds = round(microtime(true) * 1000);
                                if ($milliseconds >= $Product['Discountstart'] && $milliseconds <= $Product['DiscountEnd']) {
                                    // 在促销期 获取促销价格
                                    $data[$key]['discount'] =  $Product['discount'];
                                }
                            }
                        }

                        // 这是课程订单
                        // 传递课程ID 查询订单价格和优惠价格
                    } elseif ($OrderNo['Type'] == 2) {
                        // 这是文章
                    } elseif ($OrderNo['Type'] == 3) {
                        // 这是问答
                    }

                    // 查询订单价格  优惠价格
                    # code...
                }

                echo json_encode(retur('成功', $data));
            }
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    public function setChannelcode()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $test = self::testandverify();
        if (isset($test) && $test['administrators'] == 1) {
            //查詢別名是否存在
            //需要表名
            $from = $data['from'];
            $counter = 0;
            while ($counter < 1) {
                $code = strtoupper($data['prefix'] . '-' . substr(Uuid::uuid4()->toString(), 0, 8));
                // 这里的代码将执行一次
                $num = $this->model->onfetch('*', $from, 1, ['code' => $code])['data'];
                if (!$num['dex_Order']) {
                    $data['code'] = $code;
                    $counter++;
                }
            }
            unset($data['from'], $data['prefix']);
            // 创建
            // 发送表名 需要添加的数组
            $arr = $this->model->sqladd($from, $data);
            // 接收到参数发送给增加的
            echo json_encode($arr);
        } else {
            echo json_encode(retur('失败', '非法访问', 3000));
        }
    }
    // 获取优惠卷分页
    public function getChannelcode()
    {
        //接收参数  页码  每页记录数  排序参数 倒序还是顺序
        //这样的话  首页  直接获取最近50条好了
        $data = json_decode(file_get_contents('php://input'), true);
        // 接收分页参数
        $perPage = isset($data['perPage']) ? $data['perPage'] : 1; // 默认每页记录数为 10
        $page = isset($data['page']) ? $data['page'] : 1; // 默认第一页
        $offset = ($page - 1) * $perPage;
        $order = isset($data['offset']) ? $data['offset'] : 'DESC'; // 默认降序排序
        $condition = [];
        if (isset($data['condition'])) {
            $condition = $data['condition'];
        }

        $arr = $this->model->fetchPage('dex_Channelcode', 'id', $offset, $perPage, $order, $condition);
        $lRowCount = $this->model->getTotalRowCount('dex_Channelcode', $condition);
        if ($arr['code'] == 200) {
            echo json_encode(retur($lRowCount, $arr['data']));
        } else {
            echo json_encode(retur($lRowCount, $arr['data'], 404));
        }
    }
    // 获取账户变动
    // 不包含获取钱包地址
    public function getdexrecord()
    {
        //接收参数  页码  每页记录数  排序参数 倒序还是顺序
        //这样的话  首页  直接获取最近50条好了
        $data = json_decode(file_get_contents('php://input'), true);
        $test = self::testandverify();
        // 接收分页参数
        try {
            $perPage = isset($data['perPage']) ? $data['perPage'] : 1; // 默认每页记录数为 10
            $page = isset($data['page']) ? $data['page'] : 1; // 默认第一页
            $offset = ($page - 1) * $perPage;
            $order = isset($data['offset']) ? $data['offset'] : 'DESC'; // 默认降序排序
            $condition = [];
            if (isset($data['condition'])) {
                $condition = $data['condition'];
            }
            if (!isset($data['condition']['type'])) {
                $condition['type >'] = 1;
            }
            $condition['userid'] = $test['id'];

            $arr = $this->model->fetchPage('dex_record', 'id', $offset, $perPage, $order, $condition);
            $lRowCount = $this->model->getTotalRowCount('dex_record', $condition);
            if ($arr['code'] == 200) {

                foreach ($arr['data'] as $key => $value) {
                    $arr['data'][$key]['value'] = bcadd($arr['data'][$key]['value'], '0', 1);

                    $arr['data'][$key]['timestamp'] = date('Y-m-d H:i:s', $value['timestamp']);
                    $arr['data'][$key]['notes'] = json_decode($value['notes'], true);
                    if (isset($arr['data'][$key]['notes']['Balanceafterrecharge'])) {
                        $arr['data'][$key]['notes']['Balanceafterrecharge'] = bcadd($arr['data'][$key]['notes']['Balanceafterrecharge'], '0', 1);
                    }
                }
                echo json_encode(retur($lRowCount, $arr['data']));
            } else {
                echo json_encode(retur($lRowCount, $arr['data'], 404));
            }
        } catch (\Throwable $th) {
            echo json_encode(retur('错误', '未知错误', 404));
        }
    }

    // 获取订单分页
    public function getOrderpage()
    {
        //接收参数  页码  每页记录数  排序参数 倒序还是顺序
        //这样的话  首页  直接获取最近50条好了
        $data = json_decode(file_get_contents('php://input'), true);
        // 接收分页参数
        $perPage = isset($data['perPage']) ? $data['perPage'] : 1; // 默认每页记录数为 10
        $page = isset($data['page']) ? $data['page'] : 1; // 默认第一页
        $offset = ($page - 1) * $perPage;
        $order = isset($data['offset']) ? $data['offset'] : 'DESC'; // 默认降序排序
        $condition = [];
        if (isset($data['condition'])) {
            $condition = $data['condition'];
        }

        $arr = $this->model->fetchPage('dex_Order', 'id', $offset, $perPage, $order, $condition);
        $lRowCount = $this->model->getTotalRowCount('dex_Order', $condition);

        if ($arr['code'] == 200) {
            foreach ($arr['data'] as $key => $value) {
                $path = '';
                $from = '';
                $category =  '';
                if ($arr['data'][$key]['Type'] == 1) {
                    //课程
                    $path = 'course';
                    $from = 'dex_CourseTable';
                    $category =  'dex_CourseTypes';
                } else {
                    //文章
                    $path = 'article';
                    $from = 'dex_article';
                    $category =  'dex_category';
                }
                $Product = $this->model->onfetch('*', $from, 1, ['id' => $arr['data'][$key]['ProductID']])['data'];

                if ($Product) {
                    //查询别名
                    $alias =  $this->model->onfetch('*', $category, 1, ['id' => $Product['pid']])['data'];

                    if ($alias) {
                        $arr['data'][$key]['path'] = '/' . $path . '/' . $alias['alias'] . '/' . $arr['data'][$key]['ProductID'];
                    }
                }

                if (isset($value['Orderdetails'])) {
                    $arr['data'][$key]['Orderdetails'] = json_decode($value['Orderdetails']);
                }
            }


            echo json_encode(retur($lRowCount, $arr['data']));
        } else {
            echo json_encode(retur($lRowCount, $arr['data'], 404));
        }
    }
    // 订单结算
    public function Ordersettlement()
    {
        //
        $data = json_decode(file_get_contents('php://input'), true);




        try {
            $test = self::testandverify();
            // 先查询订单
            $OrderNo = $this->model->onfetch('*', 'dex_Order', 1, ['OrderNo' => $data['OrderNo']])['data'];

            $text = '';
            // 原始价格
            $originalprice = 0;
            // 最终价格
            $value = 0;
            // 记录
            $record = [];
            // 商家ID
            $MerchantID = '';
            $package = false;
            if ($OrderNo && $OrderNo['Status'] == 0) {
                if ($OrderNo['Type'] == 1) {
                    //课程
                    // 查询商品
                    $Product = $this->model->onfetch('*', 'dex_CourseTable', 1, ['id' => $OrderNo['ProductID']])['data'];

                    if ($Product) {
                        if ($Product['Relatedcourses']) {
                            $package = json_decode($Product['Relatedcourses'], true);
                        }
                        $MerchantID = $Product['admin'];
                        $record['Price'] = $Product['Price'];
                        $originalprice = $Product['Price'];
                        $value =  $Product['Price'];
                        if ($Product['Discountstart'] && $Product['DiscountEnd']) {
                            //促销是否有效  生成时间戳
                            $milliseconds = round(microtime(true) * 1000);
                            if ($milliseconds >= $Product['Discountstart'] && $milliseconds <= $Product['DiscountEnd']) {
                                // 在促销期 获取促销价格
                                $value =  $Product['discount'] > 0 ? $Product['discount'] : '0';
                                // 这里要知道优惠了多少
                                $discount = bcsub($Product['Price'], $Product['discount'], 18) > 0 ? bcsub($Product['Price'], $Product['discount'], 18) : $Product['Price'];
                                $record['activity'] = $discount;
                            }
                        }
                    } else {
                        $text .= '商品不存在';
                    }
                } else if ($OrderNo['Type'] == 2) {
                    //文章 dex_article
                    $Product = $this->model->onfetch('*', 'dex_article', 1, ['id' => $OrderNo['ProductID']])['data'];
                    $value =  $Product['Price'];
                    $originalprice = $Product['Price'];
                } else {
                    // 其他错误
                    echo json_encode(retur('失败', '订单非法', 5000));
                    exit;
                }
                //优惠券
                if ($data['couponskey'] && $value > 0) {
                    //判断使用条件
                    $couponskey = $this->model->onfetch('*', 'dex_couponskey', 1, ['coupkey' => $data['couponskey']])['data'];
                    if ($couponskey && $couponskey['userid'] == $test['id'] && $couponskey['state'] == 1) {
                        // 查询优惠券条件dex_coupons
                        $coupons = $this->model->onfetch('*', 'dex_coupons', 1, ['id' => $couponskey['coupid']])['data'];
                        if ($coupons['Category'] == 0 || ($coupons['Category'] == $OrderNo['Type'] && ($coupons['ProductID'] == $OrderNo['ProductID'] || $coupons['ProductID'] == 0))) {

                            $milliseconds = round(microtime(true) * 1000);
                            if (($milliseconds >= $coupons['start_time'] || $coupons['start_time'] == 0) && ($milliseconds <= $coupons['end_time'] || $coupons['end_time'] == 0)) {
                                //可以使用的优惠券
                                // 计算优惠的价格
                                // 计算应该支付的余额Conditions
                                if ($coupons['Conditions'] < $originalprice) {
                                    $discount = bcsub($value,  $coupons['Amount'], 18) > 0 ? $coupons['Amount'] : $value;
                                    $value =  bcsub($value, $coupons['Amount'], 18) > 0 ? bcsub($value, $coupons['Amount'], 18) : '0';
                                    $record['couponsAmount'] = $discount;
                                    $record['couponskey'] = $data['couponskey'];
                                    // 修改优惠券状态
                                    $this->model->onchange('dex_couponskey', ['state' =>  2], ['coupkey' => $data['couponskey']]);
                                } else {
                                    $text .= '商品金额低于优惠券使用条件';
                                }

                                // $value =  $Product['discount'];
                            } else {
                                $text .= '优惠券不在有效期';
                            }
                        } else {
                            $text .= '优惠券不适用此商品';
                        }
                    } else {
                        $text .= '此优惠券已经使用或不属于您';
                    }
                }
                $Channelcodeavailable = false;
                $Officialchannelcode = false;
                $Merchantrevenue = '0';
                $allocation = '0';
                $BeneficiaryID = 0;
                // 处理渠道码
                if ($data['Channelcode'] && $value > 0) {

                    // 渠道码
                    // 渠道码的分成这个就要分为官方  还是商家了
                    // 如果是官方的计算方式  官方收益减去分配利益大于0  那么就全额支付 如果小于0 那么就支付商家利益给用户
                    $Channelcode = $this->model->onfetch('*', 'dex_Channelcode', 1, ['code' => $data['Channelcode']])['data'];

                    $milliseconds = round(microtime(true) * 1000);
                    if (($milliseconds >= $Channelcode['start_time'] || $Channelcode['start_time'] == 0) && ($milliseconds <= $Channelcode['end_time'] || $Channelcode['end_time'] == 0) && ($Channelcode['userid'] == 0 || $Channelcode['userid'] == $test['id']) && $Channelcode['state'] == 1) {
                        //
                        if ($Channelcode['Category'] == 0 || ($Channelcode['Category'] == $OrderNo['Type'] && ($Channelcode['ProductID'] == $OrderNo['ProductID'] || $Channelcode['ProductID'] == 0))) {
                            // 可以使用
                            $Channelcodeavailable = true;

                            if ($Channelcode['Belong'] == 1) {
                                // 官方的会亏损但是 保障了商家的收益 所以不要发通用的
                                // 官方渠道 直接锁定商家收益
                                $Officialchannelcode = true;
                                $Merchantrevenue = $value * 0.7;
                            }
                            $allocation = $Channelcode['Shareratio'];
                            $BeneficiaryID = $Channelcode['BeneficiaryID'];
                            $discount = bcsub($value,  $Channelcode['Amount'], 18) > 0 ? $Channelcode['Amount'] : $value;
                            $value =  bcsub($value, $Channelcode['Amount'], 18) > 0 ? bcsub($value, $Channelcode['Amount'], 18) : '0';
                            $record['ChannelAmount'] = $discount;
                            $record['Channelcode'] = $data['Channelcode'];
                            // 判断渠道码是否可以复用
                            if ($Channelcode['Reuse'] == 2) {
                                $this->model->onchange('dex_Channelcode', ['state' =>  2], ['code' => $data['Channelcode']]);
                            }
                        }
                    }
                }
                if ($test['balance'] >= $value) {
                    if ($text) {
                        echo json_encode(retur('失败', $text, 9000));
                        exit;
                    }
                    // 修改用户的余额
                    date_default_timezone_set('Asia/Singapore');
                    // 获取新加坡时区的当前时间戳
                    $starttime = time();
                    $Balanceafterrecharge = bcsub($test['balance'], $value, 18);
                    $notes = ['OrderNo' => $data['OrderNo'],  'Balancebeforerecharge' => $test['balance'], 'Balanceafterrecharge' => $Balanceafterrecharge];
                    $this->model->sqladd('dex_record', ['notes' => $notes, 'userid' => $test['id'], 'type' => '3', 'operation' => '订单支付', 'value' => $value, 'timestamp' => $starttime]);
                    //   学分等于现在的学分
                    $integral =  bcadd($test['integral'], bcdiv($value, '10', 18), 18);
                    $this->model->onchange('dex_user', ['integral' => $integral, 'balance' => $Balanceafterrecharge], ['id' => $test['id']]);
                    // 修改订单状态
                    $Beneficiaries = '0';
                    $record['value'] = $value;
                    if ($Channelcodeavailable) {
                        //  修1改订单 添加余额变动信息(用户)(商家)(受益人) 记录收益
                        $Beneficiaries = bcmul(bcdiv($value, '100', 10), $allocation, 18);
                        if (!$Officialchannelcode) {
                            //非官方渠道  现在计算商家的收益
                            $Merchantrevenue = ($value - $Beneficiaries) * 0.7;
                        }
                        if ($BeneficiaryID != 0) {
                            // 受益人ID不等于0
                            $Beneficiary = $this->model->onfetch('*', 'dex_user', 1, ['id' => $BeneficiaryID])['data'];
                            if ($Beneficiary) {

                                $Rechargeamount = bcadd($Beneficiary['balance'], $Beneficiaries, 18);
                                $notes = ['OrderNo' => $data['OrderNo'],  'Balancebeforerecharge' => $Beneficiary['balance'], 'Balanceafterrecharge' => $Rechargeamount];
                                $this->model->sqladd('dex_record', ['notes' => $notes, 'userid' =>  $BeneficiaryID, 'type' => '4', 'operation' => '渠道收益', 'value' => $Beneficiaries, 'timestamp' => $starttime]);
                                $this->model->onchange('dex_user', ['balance' =>  $Rechargeamount], ['id' => $BeneficiaryID]);
                            }
                        }
                    } else {
                        // 没有渠道码 直接修改订单 添加余额变动信息(商家) 记录收益
                        $Merchantrevenue = $value * 0.7;
                    }
                    // 总   50  分配   受益人5元 固定   商家   官方3成  固定
                    $userinfo = $this->model->onfetch('*', 'dex_user', 1, ['id' => $MerchantID])['data'];
                    $Rechargeamount = bcadd($userinfo['balance'], $Merchantrevenue, 18);
                    $notes = ['OrderNo' => $data['OrderNo'],  'Balancebeforerecharge' => $userinfo['balance'], 'Balanceafterrecharge' => $Rechargeamount];
                    $this->model->sqladd('dex_record', ['notes' => $notes, 'userid' =>  $MerchantID, 'type' => '4', 'operation' => '订单收益', 'value' => $Merchantrevenue, 'timestamp' => $starttime]);
                    $this->model->onchange('dex_user', ['balance' => $Rechargeamount], ['id' => $MerchantID]);
                    // 用户  商家  受益人  解决完了  该修改订单状态了
                    $record['value'] = bcadd($record['value'], '0', 1);
                    $this->model->onchange('dex_Order', ['Status' => 1, 'Orderdetails' => $record], ['OrderNo' => $data['OrderNo']]);
                    // 修改订单状态  添加收益记录
                    // 记录收益还是亏损
                    $Websiterevenue = $value - $Merchantrevenue - $Beneficiaries;
                    $this->model->sqladd('dex_SiteIncome', ['value' => $value, 'Merchantincome' => $Merchantrevenue, 'beneficiary' =>  $Beneficiaries, 'Websiterevenue' => $Websiterevenue, 'OrderNo' => $data['OrderNo']]);
                    // 这里添加套餐的订单 目前只支持课程套餐
                    if ($package) {
                        //套餐存在
                        // 订单相同就好了
                        foreach ($package as  $value) {
                            $name = $this->model->onfetch('*', 'dex_CourseTable', 1, ['id' => $value])['data'];
                            if ($name) {
                                $record = ['Price' => $name['Price'], 'value' => '0'];
                                $name = $name['title'];
                                $this->model->sqladd('dex_Order', ['name' => $name, 'OrderNo' => $data['OrderNo'], 'Type' => $OrderNo['Type'], 'ProductID' => $value,  'user' => $test['id'], 'Status' => 1, 'Orderdetails' => $record]);
                            }
                        }
                    }
                    echo json_encode(retur('成功', 'ok'));
                } else {
                    echo json_encode(retur('失败', '余额不足', 9000));
                }
            } else {
                echo json_encode(retur('失败', '订单错误', 504));
            }
        } catch (\Throwable $th) {
            //throw $th;
            $errorMessage = $th->getMessage();
            json_encode(retur('程序错误', $errorMessage, 3001));
        }
    }
    // 获取回答条数 和回答条数
    public function Numberofquestionsandanswers()
    {
        try {
            // dex_qatags
            $userinfo = $this->model->onfetch('*', 'dex_qatags', 9)['data'];
            $issues = $this->model->getTotalRowCount('dex_issueslist');
            $answer = $this->model->getTotalRowCount('dex_answer');
            echo json_encode(retur('成功', ['issues' => $issues, 'answer' => $answer, 'tag' => $userinfo]));
        } catch (\Throwable $th) {
            echo json_encode(retur('成功', $th, 989));
        }
    }
    // 搜索
    public function search()
    {
        //接收参数  页码  每页记录数  排序参数 倒序还是顺序
        //这样的话  首页  直接获取最近50条好了
        $data = json_decode(file_get_contents('php://input'), true);
        // 接收分页参数
        $perPage = isset($data['perPage']) ? $data['perPage'] : 1; // 默认每页记录数为 10
        $page = isset($data['page']) ? $data['page'] : 1; // 默认第一页
        $offset = ($page - 1) * $perPage;
        $order = isset($data['offset']) ? $data['offset'] : 'DESC'; // 默认降序排序
        $data['tosort'] = isset($data['tosort']) ? $data['tosort'] : 'time';
        $orderByColumn = 'id';
        if ($data['tosort'] == 'time') {
            $orderByColumn = 'id';
        } elseif ($data['tosort'] == 'popularity') {
            $orderByColumn = 'PageView';
        }
        $from = 'dex_article';
        $searchColumns = ['title', 'content', 'keywords'];
        if ($data['type'] == 'article') {
            $from = 'dex_article';
            $searchColumns = ['title', 'content', 'keywords'];
        } elseif ($data['type'] == 'course') {
            $from = 'dex_CourseTable';
            $searchColumns = ['title', 'content'];
        } elseif ($data['type'] == 'qaarticle') {
            $from = 'dex_issueslist';
            $searchColumns = ['title', 'content'];
        }
        // 后面处理下用户信息  分类
        $arr = $this->model->searchInTable($from, $searchColumns, $data['keyword'], $offset, $perPage,  $orderByColumn, $order);
        if ($arr['code'] == 200) {
            $list = $arr['data']['data'];
            $total_count = $arr['data']['total_count'];
            $newfrom = '';
            if ($data['type'] == 'article') {
                $newfrom = 'dex_category';
            } elseif ($data['type'] == 'course') {
                $newfrom = 'dex_CourseTypes';
            } elseif ($data['type'] == 'qaarticle') {
                $newfrom = 'dex_ification';
            }
            foreach ($list as $key => $value) {
                // 获取分类
                $cate = $this->model->onfetch('*', $newfrom, 1, ['id' => $value['pid']])['data'];
                $list[$key]['pidname'] = $cate['name'];
                $list[$key]['alias'] = $cate['alias'];
                # code...
                if ($data['type'] == 'article' || $data['type'] == 'qaarticle') {
                    $list[$key]['user'] =  $this->model->onfetch('*', 'dex_user', 1, ['id' => $value['userid']])['data'];
                } elseif ($data['type'] == 'course') {
                    $list[$key]['user'] =  $this->model->onfetch('*', 'dex_user', 1, ['id' => $value['admin']])['data'];
                }
                unset($list[$key]['user']['password'], $list[$key]['user']['balance'], $list[$key]['user']['integral']);
            }
            echo json_encode(retur($total_count, $list));
        } else {
            echo json_encode(retur(0, []));
        }
    }
    public function mail()
    {
        $template_path = __DIR__ . '/../mail/Registration.html'; // 替换为模板文件的实际路径
        $template_content = file_get_contents($template_path);
        // $template_content = str_replace('{{username}}', $data['username'], $template_content);

        $arr =  $this->Toquill->mail('3005779@qq.com', 'admin', '欢迎来到DEXC区块链开发者社区', $template_content);
        echo json_encode(retur('结果', $arr));
    }
}