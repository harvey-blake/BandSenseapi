<?php

namespace Db;

use PDO;
use function common\dump;

class Query
{
    protected PDO $db;
    protected string $table;
    protected string $field = '*';
    protected int $limit = 10;
    // 查询条件
    protected array $opt = [];
    protected array $conditions = [];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // 获取当前数据表名称
    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    // 设置字段
    public function field(string $field = '*'): self
    {
        $this->field = $field;
        return $this;
    }

    // 查询数量
    public function limit(int $limit = 10): self
    {
        $this->limit = $limit;
        $this->opt['limit'] = " LIMIT $limit";
        return $this;
    }

    // 设置当前页
    public function page(int $currPage = 1): self
    {
        // 当前显示第3页: offset = (currpage  - 1) * $limit
        $this->opt['offset'] = ' OFFSET ' .  ($currPage - 1) * $this->limit;
        return $this;
    }

    // 查询条件
    // 修改后的 where 方法
    public function where(array $conditions): self
    {

        $conditionsString = [];

        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                $inValues = [];
                foreach ($value as $singleValue) {
                    $inValues[] = $this->db->quote($singleValue);
                }
                $conditionsString[] = "$column IN (" . implode(', ', $inValues) . ")";
            } else {
                // 判断是否包含操作符
                if (strpos($column, ' ') !== false) {
                    $conditionsString[] = "$column " . $this->db->quote($value);
                } else {
                    $conditionsString[] = "$column = " . $this->db->quote($value);
                }
            }
        }

        $whereClause = implode(' AND ', $conditionsString);

        // 将构建好的条件字符串存入 $this->opt['where']
        $this->opt['where'] = $whereClause ? " WHERE $whereClause" : '';

        return $this;
    }


    // 排序
    public function order(string $field, string $order = 'DESC'): self
    {
        $this->opt['order'] = " ORDER BY $field $order";
        return $this;
    }

    // 获取条数
    public function count()
    {
        $sql = 'SELECT COUNT(*) as total_count FROM ' . $this->table;
        $sql .= $this->opt['where'] ?? null;
        // dump($sql);
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        // 清空条件
        $this->opt = [];
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        // 返回符合条件的记录总数
        return $result['total_count'];
    }
    // 获取全部数据
    public function select()
    {
        $sql = 'SELECT ' . $this->field . ' FROM ' . $this->table;
        $sql .= $this->opt['where'] ?? null;
        $sql .= $this->opt['order'] ?? null;
        $sql .= $this->opt['limit'] ?? null;
        $sql .= $this->opt['offset'] ?? null;
        // dump($sql);
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        // 清空条件
        $this->opt = [];

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // 获取单条
    public function find()
    {
        $sql = 'SELECT ' . $this->field . ' FROM ' . $this->table;
        $sql .= $this->opt['where'] ?? null;


        // dump($sql);

        $stmt = $this->db->prepare($sql);

        $stmt->execute();

        // 清空条件
        $this->opt = [];


        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    // 新增数据
    public function insert(array $data): int
    {
        $data = array_filter($data, function ($val) {
            return $val != '' && $val !== null;
        });
        $str = '';
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = addslashes(json_encode($value)); // 将数组转为 JSON 字符串
            }
            $str .= $key . " = '" . $value . "', ";
        }
        $sql = 'INSERT ' . $this->table . ' SET ' . rtrim($str, ', ');
        dump($sql);
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }

    // 更新数据
    public function update(array $data): int
    {
        $data = array_filter($data, function ($val) {
            return $val != '' && $val !== null;
        });
        $str = '';
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = addslashes(json_encode($value)); // 将数组转为 JSON 字符串
            }
            $str .= $key . " = '" . $value . "', ";
        }
        $sql = 'UPDATE ' . $this->table . ' SET ' . rtrim($str, ', ');
        $sql .= $this->opt['where'] ?? die('禁止无条件更新');
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }

    // 删除操作（禁止无条件）
    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->table;
        $sql .= $this->opt['where'] ?? die('禁止无条件删除');

        $stmt = $this->db->prepare($sql);

        $stmt->execute();

        return $stmt->rowCount();
    }
}
