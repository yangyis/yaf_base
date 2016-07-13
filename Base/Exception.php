<?php
namespace Base;
/***
 * 异常类型
 * @author Yangyi <yi.yang@vhall.com>
 * @create_time 2016/06/06
 * @modify_time 2016/06/15
 */
class Exception{
    public static function error($msg='error', $code = 500){
        throw new \Exception($msg, $code);
    }
}