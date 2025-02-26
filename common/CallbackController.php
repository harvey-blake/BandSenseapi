<?php

namespace common;

class CallbackController
{
    public $result; // 用于存储方法调用结果

    public function __invoke($error, $result)
    {
        if ($error) {
            throw $error;
        }

        // 处理可能是 BigInteger 或 GMP 类型的数据
        if ($result instanceof \phpseclib\Math\BigInteger) {
            // 提取 BigInteger 的值并将其转化为字符串
            $this->result = $result->toString();
        } elseif ($result instanceof GMP) {
            // 如果是 GMP 对象，使用 gmp_strval 转换为字符串
            $this->result = gmp_strval($result);
        } else {
            // 其他类型的处理
            $this->result = $result;
        }
    }
}
