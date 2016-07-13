<?php
namespace Base\Log;
use Yaf\Registry;
/***
 * 异常类型
 * @author Yangyi <yi.yang@vhall.com>
 * @create_time 2016/06/06
 * @modify_time 2016/06/15
 */
class File{
    
    /**
     * 写入日志
     * @param string $content
     * @param string $subdir
     */
    public static function add($content, $subdir) {
        $config   = Registry::get('config');
    	$log_path = $config['log']['directory'];
        if (!is_dir($log_path) or !is_writable($log_path)) {
            return false;
        }
    	$log_path = $log_path . '/' . $subdir . '/';
        if (!is_dir($log_path) or !is_writable($log_path)) {     
            mkdir($log_path, 0777);
        }
        $file_name = $log_path . date('Y-m-d') . '.log';
        return file_put_contents($file_name, $content, FILE_APPEND);
    }
}