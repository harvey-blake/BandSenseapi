<?php

namespace app\edu\v1\controller;

use function common\retur;

use PDO;

class ModelController
{
    protected  $db;
    public function __construct()
    {

        $dsn = CONFIG['database']['type'] . ':dbname=' . CONFIG['database']['dbname'] . '; host=' . CONFIG['database']['host'];
        $username = CONFIG['database']['username'];
        $password = CONFIG['database']['password'];
        $this->db = new PDO($dsn, $username, $password);;
    }

    // 查询全部   查询单条   增加数据   修改数据  删除数据    四个公共方法  即可返回所有需要的数据
    //使用JS进行post 提交
    //查询一条、
    // SQL 直接传入进来 查询一个还是查询所有
    public function fetch($data)
    {
        // 需要三个个条件   返回参数   表名  查询条件
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // 直接传入sql
        $sql = sprintf('SELECT %s FROM %s WHERE %s', $data[0], $data[1], $data[2]);
        if ($data[2] == '*') {
            $sql = sprintf('SELECT %s FROM %s ', $data[0], $data[1]);
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        // 判断一下 需要返回什么   1为结果 否则为判断是否存在
        // echo $data[3];
        if ($data[3] === 1) {
            return $stmt->fetch();
        } else if ($data[3] === 9) {
            return $stmt->fetchAll();
        } else {
            return $stmt->rowCount();
        }
    }

    // public function fetchgo($data)
    // {
    //     $sql = sprintf('SELECT COUNT(%s) AS %s FROM %s', $data[0], $data[1], $data[2]);
    //     $stmt = $this->db->prepare($sql);
    //     $stmt->execute();
    //     $stmt->bindColumn($data[1], $total);
    //     $stmt->fetch(PDO::FETCH_ASSOC);
    //     return $total;
    // }


    //获取分页数据 需要知道表名 开始值  结束值
    // $tableName: 数据库中的表名，你希望从这个表中获取数据。
    // $orderByColumn: 你希望按照哪一列来排序结果。
    // $startIndex: 数据查询的起始索引，用于分页。
    // $limit: 每页的记录数，用于分页。
    // $conditions: 一个可选的数组，包含用于筛选数据的查询条件。

    // 参数 thumbnailCondition，用于指定 thumbnail 字段的条件。可以传递三种值：hasValue（需要查询有值）、noValue（需要查询没有值）和 null
    public function fetchPage($tableName, $orderByColumn, $startIndex, $limit, $order = 'ASC', $conditions = null, $thumbnailCondition = null)
    {
        try {
            $sql = "SELECT * FROM $tableName";

            $bindingValues = [];

            if ($conditions !== null && is_array($conditions) && count($conditions) > 0) {
                $sql .= " WHERE ";
                $conditionsClauses = [];

                foreach ($conditions as $column => $value) {
                    if (is_array($value)) {
                        $operator = 'IN';
                        $conditionsClauses[] = "$column $operator (" . implode(", ", array_fill(0, count($value), "?")) . ")";
                        $bindingValues = array_merge($bindingValues, $value);
                    } else {
                        // Check for comparison operators in the column name
                        if (preg_match('/^(.+)\s*([<>]=?)$/', $column, $matches)) {
                            $column = $matches[1];
                            $operator = $matches[2];
                        } else {
                            $operator = '=';
                        }
                        $conditionsClauses[] = "$column $operator ?";
                        $bindingValues[] = $value;
                    }
                }

                $sql .= implode(" AND ", $conditionsClauses); // Use "AND" for more accurate matching
            }

            // 处理 thumbnail 条件
            if ($thumbnailCondition === 'hasValue') {
                $sql .= ($conditions !== null && count($conditions) > 0) ? " AND thumbnail IS NOT NULL" : " WHERE thumbnail IS NOT NULL";
            } elseif ($thumbnailCondition === 'noValue') {
                $sql .= ($conditions !== null && count($conditions) > 0) ? " AND thumbnail IS NULL" : " WHERE thumbnail IS NULL";
            }

            $sql .= " ORDER BY $orderByColumn $order LIMIT ?, ?";

            $stmt = $this->db->prepare($sql);

            // 绑定参数
            $paramCount = count($bindingValues);
            for ($i = 0; $i < $paramCount; $i++) {
                $stmt->bindValue($i + 1, $bindingValues[$i]);
            }

            $stmt->bindValue($paramCount + 1, (int) $startIndex, PDO::PARAM_INT);
            $stmt->bindValue($paramCount + 2, (int) $limit, PDO::PARAM_INT);
            $stmt->execute();
            $staffs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($staffs) === 0) {
                return retur('查询结果为空', [], 909);
            } else {
                return retur('成功了', $staffs);
            }
        } catch (\Throwable $th) {
            return retur('错误', $th->getMessage(), 4001);
        }
    }

    // 獲取縂條數

    public function getTotalRowCount($tableName, $conditions = null)
    {
        try {
            $sqlCount = "SELECT COUNT(*) as total FROM $tableName";

            $bindings = [];
            if ($conditions !== null && is_array($conditions) && count($conditions) > 0) {
                $sqlCount .= " WHERE ";
                $conditionsClauses = [];

                foreach ($conditions as $column => $value) {
                    if (is_array($value)) {
                        $operator = 'IN';
                        $conditionsClauses[] = "$column $operator (" . implode(", ", array_fill(0, count($value), "?")) . ")";
                        $bindings = array_merge($bindings, $value);
                    } else {
                        // Check for comparison operators in the column name
                        if (preg_match('/^(.+)\s*([<>]=?)$/', $column, $matches)) {
                            $column = $matches[1];
                            $operator = $matches[2];
                        } else {
                            $operator = '=';
                        }
                        $conditionsClauses[] = "$column $operator ?";
                        $bindings[] = $value;
                    }
                }

                $sqlCount .= implode(" AND ", $conditionsClauses); // Use "AND" for more accurate matching
            }


            $stmtCount = $this->db->prepare($sqlCount);
            for ($i = 0; $i < count($bindings); $i++) {
                $stmtCount->bindValue($i + 1, $bindings[$i]);
            }
            $stmtCount->execute();

            return $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        } catch (\Throwable $th) {
            return 0; // 返回默认值或错误处理
        }
    }

    //插入数据
    public function adddata($data)
    {
        // 值需要表名字   添加的参数为拼接字符串
        $sql = sprintf('INSERT %s SET  %s', $data[0], $data[1]);
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            //返回新增的主键ID


            return  $this->db->lastInsertId();
            // echo '插入数据'.'<hr>';
        } else {
            return   $stmt->errorInfo();
        }
    }
    //插入数据 表名 数据  传入什么比较方便 JSON{表名=>'内容'}  可以直接插入
    public function sqladd($table, $data)
    {
        // 过滤掉空值和 null 值
        $filteredData = array_filter($data, function ($val) {
            return $val !== '' && $val !== null;
        });

        // 转换数组值为 JSON
        $filteredData = array_map(function ($val) {
            if (is_array($val)) {
                return json_encode($val);
            }
            return $val;
        }, $filteredData);

        if (count($filteredData) === 0) {
            echo json_encode(retur('出错了', '数据为空', 909));
            exit;
        }

        $columns = implode(', ', array_keys($filteredData));
        $placeholders = implode(', ', array_map(function ($column) {
            return ':' . $column;
        }, array_keys($filteredData)));

        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, $columns, $placeholders);
        $stmt = $this->db->prepare($sql);

        foreach ($filteredData as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }

        if ($stmt->execute()) {
            return retur('成功了', $this->db->lastInsertId());
        } else {
            return retur('错误', $sql, 4001);
        }
    }

    // 查询数据
    // 4个条件   返回参数  表名  查询类型  条件
    // 前两个都为 sting   最后一个调教
    public function onfetch($result, $Table, $types, $array = [], $ele = '')
    {
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // 构建基本的 SQL 查询语句
        $sql = "SELECT $result FROM $Table";

        $params = array();
        $conditions = array();

        // 检查 $array 是否为空或为一个空字符串
        if (!empty($array) && $array != '') {
            $whereAdded = false; // 用于标记是否已经添加了 WHERE 子句
            foreach ($array as $key => $value) {
                // 解析比较运算符
                $operator = '=';
                if (preg_match('/^(.+)\s*([<>]=?)$/', $key, $matches)) {
                    $key = $matches[1];
                    $operator = $matches[2];
                }

                if (is_array($value)) {

                    // 如果值是数组，使用 IN 子句
                    $placeholders = implode(', ', array_fill(0, count($value), "?"));
                    if (!$whereAdded) {
                        $conditions[] = "WHERE $key IN ($placeholders)";
                        $whereAdded = true;
                    } else {
                        $conditions[] = "AND $key IN ($placeholders)";
                    }
                    $params = array_merge($params, $value);
                } else {
                    // 如果值不是数组，使用普通的条件
                    if (!$whereAdded) {
                        $conditions[] = "WHERE $key $operator ?";
                        $whereAdded = true;
                    } else {
                        $conditions[] = "AND $key $operator ?";
                    }
                    $params[] = $value;
                }
            }
        }

        // 如果需要查询最大值
        if ($ele != '') {
            $sql = "SELECT $result FROM $Table WHERE id=(SELECT MAX(id) FROM $Table WHERE pid=17)";
        }

        // 将所有条件组合在一起
        if (!empty($conditions)) {
            $conditionString = implode(" ", $conditions);
            $sql .= " $conditionString";
        }

        // 执行参数化查询
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        if ($types === 1) {
            return retur('成功', $stmt->fetch());
        } else if ($types === 9) {
            return retur('成功', $stmt->fetchAll());
        } else {
            return retur('错误', false, 4001);
        }
    }

    // 查询表最大的数值
    public function onfetchmax($result, $Table, $array, $id = 'id')
    {
        // 需要 返回参数  表名  条件(数组)
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $i = 1;
        $data = '';
        if (count($array) > 0) {
            foreach ($array as $key => $value) {
                if ($i < count($array)) {
                    $data .=  $key . '='  . "'" . $value   . "' AND ";
                } else {
                    $data .= $key . '='  . "'" . $value   . "'";
                }
                $i++;
            }
        }
        $text = 'SELECT %s FROM %s WHERE ' . $id . '=(SELECT MAX(' . $id . ')' . ' FROM %s )';
        $sql = sprintf($text, $result, $Table, $Table);
        if ($data != '') {
            $sql = sprintf($text, $result, $Table, $Table, $data);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return  retur('成功', $stmt->fetch());
        } else {
            return  retur('错误', $stmt->errorInfo(), 4001);
        }
    }
    // 修改数据 表名
    public function onchange($from, $array, $WHERE)
    {
        $array = array_filter($array, function ($val) {
            return $val !== null && $val !== '';
        });

        if (count($array) == 0) {
            return retur('出错了', '数据为空', 909);
        }

        $data = '';
        $dataParams = [];
        foreach ($array as $key => $value) {
            // 如果值是数组，则将其转换为 JSON 格式
            if (is_array($value)) {
                $value = json_encode($value);
            }

            $data .= "$key=?,";
            $dataParams[] = $value;
        }
        $data = rtrim($data, ',');

        $condition = '';
        $conditionParams = [];
        foreach ($WHERE as $key => $value) {
            $condition .= "$key=? AND ";
            $conditionParams[] = $value;
        }
        $condition = rtrim($condition, ' AND ');

        $sql = "UPDATE $from SET $data WHERE $condition";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($dataParams, $conditionParams));

        if ($stmt->rowCount() > 0) {
            return retur('成功', $stmt->rowCount());
        } else if ($stmt->errorInfo()[0] == '0000') {
            return retur('没有修改任何数据', $stmt->rowCount(), 4004);
        } else {
            return retur('出错了', $sql, 4003);
        }
    }


    //删除数据 表名  条件数组
    public function ondeletedata($from, $WHERE)
    {
        if (empty($WHERE)) {
            return retur('出错了', '删除条件为空', 4001); // 自定义错误码为 4001
        }

        $condition = '';
        $conditionParams = [];

        foreach ($WHERE as $key => $value) {
            if (is_array($value)) {
                // 处理值为数组的情况，构建 IN 条件
                $valuePlaceholders = implode(', ', array_fill(0, count($value), '?'));
                $condition .= "$key IN ($valuePlaceholders) AND ";
                $conditionParams = array_merge($conditionParams, $value);
            } else {
                // 处理普通条件
                $condition .= "$key=? AND ";
                $conditionParams[] = $value;
            }
        }

        $condition = rtrim($condition, ' AND ');

        $sql = "DELETE FROM $from WHERE $condition";
        $stmt = $this->db->prepare($sql);

        if ($stmt->execute($conditionParams)) {
            if ($stmt->rowCount() > 0) {
                return retur('删除成功', $stmt->rowCount(), '');
            } else {
                return retur('没有匹配的数据被删除', 0, 4002); // 自定义错误码为 4002
            }
        } else {
            return retur('出错了', $stmt->errorInfo(), 4003); // 自定义错误码为 4003
        }
    }


    //修改数据
    public function changedata($data)
    {
        // 值需要表名  需要修改的参数   修改的条件
        $sql = sprintf('UPDATE %s SET  %s WHERE %s', $data[0], $data[1], $data[2]);
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            //打印查询结果
            return  retur('成功', '', 200);
        } else {
            return  retur('出错', $stmt->errorInfo(), 4002);

            // print_r($stmt->errorInfo());
        }
    }


    //删除数据
    public function deletedata($data)
    {

        // 值需要表名   删除的条件

        $sql = sprintf('DELETE FROM %s WHERE %s', $data[0], $data[1]);
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            //  var_dump($stmt->rowCount());
            echo '删除成功' . '<hr>';
        } else {
            echo '删除失败' . '<hr>';
            // print_r($stmt->errorInfo());
        }
    }
    // 搜索
    // 表名
    // 搜索列名
    // 关键字
    // 起始
    // 结束
    // 排序
    public function searchInTable($tableName, $searchColumns, $searchKeyword, $startIndex, $limit, $orderByColumn, $order = 'ASC')
    {
        try {
            $sql = "SELECT * FROM $tableName";
            $countSql = "SELECT COUNT(*) AS total_count FROM $tableName";
            $bindingValues = [];

            $searchConditions = [];
            foreach ($searchColumns as $column) {
                $searchConditions[] = "$column LIKE ?";
                $bindingValues[] = "%$searchKeyword%";
            }

            $sql .= " WHERE (" . implode(" OR ", $searchConditions) . ")";
            $countSql .= " WHERE (" . implode(" OR ", $searchConditions) . ")";

            $sql .= " ORDER BY $orderByColumn $order LIMIT ?, ?";
            $stmt = $this->db->prepare($sql);

            $countStmt = $this->db->prepare($countSql);

            $paramCount = count($bindingValues);
            for ($i = 0; $i < $paramCount; $i++) {
                $stmt->bindValue($i + 1, $bindingValues[$i]);
                $countStmt->bindValue($i + 1, $bindingValues[$i]);
            }

            $stmt->bindValue($paramCount + 1, (int)$startIndex, PDO::PARAM_INT);
            $stmt->bindValue($paramCount + 2, (int)$limit, PDO::PARAM_INT);

            $stmt->execute();
            $searchResult = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total_count'];

            if (count($searchResult) === 0) {
                return retur('搜索结果为空', [], 909);
            } else {
                return retur('搜索成功', ['total_count' => $totalCount, 'data' => $searchResult]);
            }
        } catch (\Throwable $th) {
            return retur('错误', $th->getMessage(), 4001);
        }
    }
    public function getPDO()
    {
        return $this->db;
    }
}
