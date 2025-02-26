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
        $this->result = $result;
    }
}
