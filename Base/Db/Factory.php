<?php
namespace Base\Db;
use Base\Db\Pdo;
use Base\Exception;
use Yaf\Registry;
class Factory{   
    public static $dbConfig = array();
    public static $db = array();
    public static $separator = '_';
    public static $databaseConfig = array();
    public static $serverDb = array();
    
    /**
     * 根据数据库名称获取数据库连接
     */
    public static function getDb($dbName, $dbIndex=0, $multiTable = true){
        $db = self::$db;
        if(isset($db[$dbName][$dbIndex]) && $db[$dbName][$dbIndex] instanceof Pdo){
            return $db[$dbName][$dbIndex];
        }else{
            $dbConfig = self::getDbConfig($dbName, $dbIndex, $multiTable);
            if($dbConfig){
                self::$db[$dbName][$dbIndex] = new Pdo($dbConfig);
                return self::$db[$dbName][$dbIndex];
            }else{
                Exception::error("not found db config! dbname:$dbName dbIndex:$dbIndex", 508);
            }
        }
    }
    
    /**
     * 设置初始化数据库配置
     */
    public static function setDbConfig($config = array()){
        if(!self::$databaseConfig){
            if(!$config){
                $commonConfig = Registry::get('config');
                $config = $commonConfig['db'];
            }
            self::$databaseConfig = $config;
        }
    }
    
    /**
     * 根据数据库名称和数据库节点索引获取数据库连接配置
     */
    public static function getDbConfig($dbName, $dbIndex = 0, $multiTable = true){
        $dbConfig = self::$dbConfig;
        if(isset($dbConfig[$dbName][$dbIndex]) && is_array($dbConfig[$dbName][$dbIndex])){
            return $dbConfig[$dbName][$dbIndex];
        }else{
            $dbParams = self::$databaseConfig;
            if(isset($dbParams['dbname'][$dbName][$dbIndex])){
                $dbServerIndex = $dbParams['dbname'][$dbName][$dbIndex];
            }else{
                Exception::error("not found db server index config! dbname:$dbName dbIndex:$dbIndex", 508);
            }
            $dbConfigArr   = $dbParams['server'][$dbServerIndex];
            $_dbConfig = array();
            $_dbConfig['read'] = $dbConfigArr['read'];
            $_dbConfig['write'] = $dbConfigArr['write'];
            $_dbConfig['dbName'] = $multiTable ? $dbName.self::$separator.$dbIndex : $dbName;
            self::$dbConfig[$dbName][$dbIndex] = $_dbConfig;
            return self::$dbConfig[$dbName][$dbIndex];
        }
    }
}