<?php

// 控制器类的基类 相当于所有控制器的祖先

namespace common;

use Db\Db;
// use common\CallbackController;
use view\View;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Ramsey\Uuid\Uuid;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Validator;
use Lcobucci\JWT\Validation\Constraint\SignedWith;

class Controller
{
    // 视图对象
    protected  $myCallback;
    // 构造器
    public function __construct()
    {
        $this->myCallback = new CallbackController();
    }
}
