<?php
namespace Base\Db;
use Base\Exception;
use Base\Log\File;
/**
 * 数据库Pdo基础类
 */
class Pdo{
    private $dbHost;/*数据库IP*/
    private $dbPort;/*数据库端口*/
    private $dbUser;/*数据库用户名*/
    private $dbPwd;/*数据库密码*/
    private $dbName;/*数据库名称*/
    private $dbh;/*PDO连接句柄*/
    private $timeOut = 5;/*超时时间*/
    
    private $tableName;/*数据库表名*/
    private $tablePrefix = '';/*数据库表前缀*/
    private $tableSeparator = '_';/*数据库表前缀连接符*/
    private $primaryName;/*数据库主键名称*/
    private $writeLog = true;/*是否开启日志*/
    private $sql;/*当前执行的sql语句*/
    private $param = array();/*当前执行sql的参数*/
    private $startTime;/*当前数据库连接的开始时间*/
    private $dbReadConfig;/*数据库读库配置*/
    private $dbRead;/*数据库读库句柄*/
    private $readConnectTime = 0;/*读库连接时间*/
    private $dbWriteConfig;/*数据库写库配置*/
    private $dbWrite;/*数据库读库句柄*/
    private $writeConnectTime = 0;/*写库连接时间*/
    private $mode = 'read';/*连接数据库读写模式，read读库，write写库*/

    /**
     * 初始化构造函数
     */
    public function __construct($config = array()) {
        $this->setConfig($config)->init();
    }
    
    /**
     * 设置数据库连接配置
     */
    public function setConfig($config = array()){
        if($config && is_array($config)){
            $this->dbWriteConfig = $config['write'];
            $this->dbReadConfig  = $config['read'];
            $this->dbName        = $config['dbName'];
        }
        return $this;
    }
    
    /**
     * 设置表前缀
     */
    public function setTablePrefix($tablePrefix){
        $this->tablePrefix = $tablePrefix;
        return $this;
    }
    
    public function setTableSeparator($tableSeparator){
        $this->tableSeparator = $tableSeparator;
        return $this;
    }
    
    /**
     * 设置表的名称
     */
    public function setTableName($tableName){
        $this->tableName = $this->tablePrefix($tableName);
        return $this;
    }
    
    /**
     * 设置主键名称
     */
    public function setPrimaryName($primaryName){
        $this->primaryName = $primaryName;
        return $this;
    }
    
    /**
     * 子类的构造函数
     */
    public function init(){}
    
    /**
     * 检测连接时间是否超时
     */
    private function timeOut(){
        if($this->mode == 'write'){
            return $this->getMicrotime() - $this->writeConnectTime < $this->timeOut;
        }else{
            return $this->getMicrotime() - $this->readConnectTime < $this->timeOut;
        }
    }
    
    /**
     * 通过PDO连接数据库
     */
    public function connect(){
        $this->startTime = $this->getMicrotime();
        if($this->mode == 'write' ){
            $this->dbh = $this->dbWrite;
        }else{
            $this->dbh = $this->dbRead;
        }
        if($this->timeOut() && $this->dbh instanceof \PDO){
            return $this->dbh;
        } else {
            try {
                if( $this->mode == 'write' ){
                    $this->dbWrite = new \PDO("mysql:host={$this->dbWriteConfig['host']};port={$this->dbWriteConfig['port']};dbname={$this->dbName}",$this->dbWriteConfig['user'], $this->dbWriteConfig['password']);
                    $this->writeConnectTime = $this->getMicrotime();
                    $this->dbWrite->query("set names utf8");
                    $this->dbh = $this->dbWrite;
                }else{
                    $this->dbRead = new \PDO("mysql:host={$this->dbReadConfig['host']};port={$this->dbReadConfig['port']};dbname={$this->dbName}",$this->dbReadConfig['user'], $this->dbReadConfig['password']);
                    $this->readConnectTime = $this->getMicrotime();
                    $this->dbRead->query("set names utf8");
                    $this->dbh = $this->dbRead;
                }
            } catch(PDOException $e) {
                $this->errLog("Connection failed: {$e->getMessage()}");
                Exception::error("Connection failed!", 509);
                return false;
            }
            
            if(!$this->dbh instanceof \PDO){
                $this->errLog("Property dbh not instanceof PDO");
                Exception::error('Property dbh not instanceof PDO!', 509);
                return false;
            }else{
                return true;
            }
        }
    }
    
    /**
     * 执行未格式化的SQL语句
     */
    public function execute($sql, $param = array(), $isAffected = true){
        return $this->setRecord($this->formatSql($sql), $param, $isAffected);
    }
    
     /**
     * 执行未格式化的查询SQL语句,返回多行数据
     */
    public function select($sql, $param = array()){
        return $this->getRecords($this->formatSql($sql), $param);
    }
    
    /**
     * 执行未格式化的查询SQL语句,返回单行数据
     */
    public function find($sql, $param = array()){
        return $this->getRecord($this->formatSql($sql), $param);
    }
    
     /**
     * 执行未格式化的查询SQL语句,返回单行单列数据
     */
    public function findColumn($sql, $param = array()){
        return $this->fetchColumn($this->formatSql($sql), $param);
    }
      
    /**
     * 根据主键获取多行记录
     */
    public function get($ids = null, $field = array()){
        $sql = "SELECT ".$this->getFields($field)." FROM ".$this->tableName;
        if(!$ids){
            return array();
        }
        $sql .= " WHERE `{$this->primaryName}` ";
        if(is_array($ids)){
            $param	  = array();
            foreach($ids as $k => $id){
                if(is_numeric($id)){
                    $param[':id'.$k] = $id;
                }
            }
            return $this->getRecords($sql."IN(".implode(',', array_keys($param)).")", $param);
        }else{
            return $this->getRecord($sql."=:id", array(":id"=>$ids) );
        }
    }
    
    /**
     * 根据条件获取单行记录的单个字段
     */
    public function getColumn($where, $field = '', $order = array(), $param = array() ){
        list($_where, $_param) = $this->where($where, $param);
        if(empty($field)){
            $field = "`{$this->primaryName}`";
        }
        $sql = "SELECT {$field} FROM ".$this->tableName.$_where.$this->order($order);
        return $this->fetchColumn($sql, $_param);
    }


    /**
     * 根据条件获取单行记录
     */
    public function getRow($where, $order = array(), $field = array(), $param = array() ){
        list($_where, $_param) = $this->where($where, $param);
        $sql = "SELECT ".$this->getFields($field)." FROM ".$this->tableName.$_where.$this->order($order);
        return $this->getRecord($sql, $_param);
    }
    
    /**
     * 获取多行记录
     */
    public function getList($where, $order = array(),$field = array(), $offset = 0, $size = 0, $param = array()){
        if(!$where && $size == 0){
            $this->userErrorLog("SELECT ".$this->getFields($field)." FROM ".$this->tableName);
            return array();
        }
        list($_where, $_param) = $this->where($where, $param);
        if(empty($offset)){
            $offset = 0;
        }
        if($size > 0){
            $limit = " LIMIT $offset,$size";
        }else{
            $limit = '';
        }
        $sql = "SELECT ".$this->getFields($field)." FROM ".$this->tableName.$_where.$this->order($order).$limit;
        return $this->getRecords($sql, $_param);
    }
    
    /**
     * 获取自增主键ID
     */
    public function getPrimary(){
        return $this->insert(array($this->primaryName=>''));
    }
    
    /**
     * 根据条件更新记录
     */
    public function update($data, $where, $isAffected = true , $param = array()){
        if(!$where){
            return false;
        }
        if(is_array($data) && $data){
            list($_where, $_param) = $this->where($where, $param);
            $_data = array();
            foreach($data as $k => $v){
                $_k = ":".$k."s";
                $_data[] = "`$k` = $_k";
                $_param[$_k] = $v;
            }
            $sql = "UPDATE ".$this->tableName." SET ".implode(',', $_data).$_where;
            return $this->setRecord($sql, $_param, $isAffected);
        }else{
            return false;
        }
    }
    
    /**
     * 写入单行数据
     */
    public function insert($data, $mode = true){
        if(is_array($data) && $data){
            $_fields = array();
            $_values = array();
            $_params = array();
            foreach($data as $k => $v){
                $_fields[] = "`$k`";
                $_k = ":".$k.'s';
                $_values[] = $_k;
                $_params[$_k] = $v;
            }
            $sql = "INSERT INTO ".$this->tableName."(".implode(',', $_fields).") VALUES(".implode(',', $_values).")";
            $r = $this->setRecord($sql, $_params);
            if($r){
                if($mode){
                    return $this->getLastInsertId();
                }else{
                    return $r;
                }
            }else{
                return false;
            }
        }else{
            return false;
        }
    }
    
    /**
     * 批量根据主键更新数据
     */
    public function batchSave($data){
        if(is_array($data) && $data){
            $_fields = array();
            $_sets = array();
            $_wheres = array();
            $_params = array();
            $_i = 0 ;
            $last = count($data) - 1;
            foreach($data as $v){
                if(is_array($v) && $v){
                    $_value = array();
                    foreach($v as $key => $val){
                        if($key == $this->primaryName){
                            continue;
                        }
                        if($_i == 0){
                            $_fields[$key][] = " `$key` = CASE `{$this->primaryName}` ";
                        }
                        $_when = ':'.$key.'_key_'.$_i.'s';
                        $_then = ':'.$key.'_val_'.$_i.'s';
                        $_fields[$key][] = " WHEN $_when THEN $_then ";
                        $_params[$_when] = $v[$this->primaryName];
                        $_params[$_then] = $val;
                        if($_i == $last){
                            $_sets[$key] = implode('', $_fields[$key]);
                        }
                    }
                    $_inKey = ':in_key_'.$_i.'s';
                    $_wheres[] = $_inKey;
                    $_params[$_inKey] = $v[$this->primaryName];
                    $_i++;
                    
                }
                
            }
            $sql = "UPDATE ".$this->tableName." SET ".implode('END,', $_sets)." END WHERE `{$this->primaryName}` IN(".implode(',', $_wheres).")";
            return $this->setRecord($sql, $_params);
        }else{
            return false;
        }
    }
    
    /**
     * 批量写入，写入多行数据
     */
    public function insertBatch($data){
        if(is_array($data) && $data){
            $_fields = array();
            $_values = array();
            $_params = array();
            $_i = 0 ;
            foreach($data as $v){
                if(is_array($v) && $v){
                    $_value = array();
                    foreach($v as $key => $val){
                        if($_i == 0){
                            $_fields[] = "`$key`";
                        }
                        $_k = ":".$key.'_'.$_i.'s';
                        $_value[] = $_k;
                        $_params[$_k] = $val;
                    }
                    $_values[] = '('.implode(',', $_value).')';
                    $_i++;
                }
            }
            $sql = "INSERT INTO ".$this->tableName."(".implode(',', $_fields).") VALUES ".implode(',', $_values);
            return $this->setRecord($sql, $_params);
        }else{
            return false;
        }
    }

    /**
     * 删除数据
     */
    public function delete($where, $isAffected = true, $param = array()){
        if(!$where) {
            $this->userErrorLog("DELETE FROM ".$this->tableName);
            return false;
        }
        list($_where, $_param) = $this->where($where, $param);
        $sql = "DELETE FROM ".$this->tableName.$_where;
        return $this->setRecord($sql, $_param, $isAffected);
    }
    
    /**
     * 根据条件统计行数
     */
    public function getCount($where, $param = array()){
        list($_where, $_param) = $this->where($where, $param);
        $sql = "SELECT COUNT(1) as num FROM ".$this->tableName.$_where;
        return $this->fetchColumn($sql, $_param);
    }
    
    /**
     * 格式化sql查询字段
     */
    private function getFields($fields){
        if(is_array($fields)){
            if($fields){
                return '`'.implode('`,`', $fields).'`';
            }else{
                return "*";
            }
        }elseif(is_string($fields)){
            return $fields;
        }else{
            return "*";
        }
    }
    
    /**
     * 合成where条件
     */
    private function where($where, $param = array()){
        if(!$where){
            return array('',array());
        }elseif(is_array($where)){
            $i = 0;
            $_where = array();
            $_param = array();
            foreach($where as $k => $v ){
                if(is_array($v) && count($v) == 1){
                    $v = reset($v);
                }
                if(is_array($v) && $v){
                    $_whereIn = array();
                    $j = 0;
                    foreach ($v as $k1 => $v1){
                        $_k1 = ':'.$k.'_'.$i.'_'.$j.'s';
                        $_param[$_k1] = $v1;
                        $_whereIn[] = $_k1;
                        ++$j;
                    }
                    $_where[] = " `$k` IN(".implode(',', $_whereIn).") ";
                }else{
                    $_fields = explode(' ', $k);
                    if(isset($_fields[1]) && in_array(trim($_fields[1]), array('>','<','>=','<=','!=','like'))){
                        $_k = ':'.$_fields[0].'_'.$i.'s';
                        $_where[] = " `{$_fields[0]}` {$_fields[1]} $_k ";
                    }else{
                        $_k = ':'.$k.'_'.$i.'s';
                        $_where[] = " `$k` = $_k ";
                    }
                    if(is_array($v)){
                        $v = '';
                    }
                    $_param[$_k] = $v;
                }
                ++$i;
            }
            return array(' WHERE '.implode(' AND ', $_where), $_param);
        }elseif(is_string($where)){
            if(is_array($param) && $param){
                $_where = array();
                $_whereKey = array();
                foreach( $param as $k => $v ){
                    if( is_array($v) && $v ){
                        if( count($v) == 1 ){
                            $param[$k] = reset($v);
                        }else{
                            $_whereIn = array();
                            foreach ($v as $k1 => $v1){
                                $_k1 = $k.$k1;
                                $param[$_k1] = $v1;
                                $_whereIn[] = $_k1;
                            }
                            $_whereKey[] = $k;
                            $_where[]    = implode(',', $_whereIn);
                            unset($param[$k]);
                        }
                    }
                }
                if($_whereKey && $_where){
                    return array( ' WHERE '.str_replace($_whereKey, $_where, $where), $param );
                }else{
                    return array( ' WHERE '.$where, $param );
                }
            }  else {
                return array(' WHERE '.$where, array());
            }
        }else{
            return array('',array());
        }
    }
    
    /**
     * 排序方法
     */
    private function order($order){
        if(!$order){
            return '';
        }elseif(is_array($order)){
            $_order = array();
            foreach($order as $k => $v){
                $_order[] = " `$k` $v ";
            }
            return ' ORDER BY '.  implode(',', $_order);
        }elseif(is_string($order)){
            return ' ORDER BY '.$order;
        }else{
            return '';
        }
    }
    
    /**
     * 执行格式化好的SQL语句
     */
    private function query($sql, $param = array(), $readOnly = FALSE ){
        try{
            if(!$readOnly){
                $this->mode = 'write';
            }
            if(!$this->connect()){
                return false;
            }
            $this->sql = $sql;
            $this->param = $param;
            $query = $this->dbh->prepare($sql);
            if($query instanceof \PDOStatement){
                if( $query->errorCode() ){
                    $this->sqlLog($sql, $param);
                    $this->errLog("prepare sql error : {$sql}", $query->errorInfo());
                    Exception::error('prepare sql error!', 509);
                }
                if($param){
                    foreach($param as $key => $val){
                        $query->bindParam($key,$param[$key]);
                    }
                }
                $query->execute();
                if($query->errorCode() !== '00000' ){
                    $this->sqlLog($sql, $param);
                    $this->errLog("execute sql error : {$sql}", $query->errorInfo());
                    Exception::error('execute sql error!',509);
                }
                if($this->writeLog){
                    $this->sqlLog($sql, $param);
                }
            }else{
                $this->sqlLog($sql, $param);
                $this->errLog("prepare sql error : {$sql}", $param);
                Exception::error('prepare sql error!',509);
                return false;
            }
            return $query;
        }  catch (Exception $e){
            $this->sqlLog($sql, $param);
            $this->errLog("prepare sql error : {$e->getMessage()}");
            Exception::error('prepare sql error!',509);
            return false;
        }
    }

    /**
     * 执行语句，主要是（添加、更新，删除操作调用）
     */
    private function setRecord( $sql, $param = array(), $isAffected = true ) {
        $this->writeLog = false;
        $query = $this->query($sql, $param, FALSE);
        $this->writeLog = true;
        if($isAffected){
            $r = $query->rowCount();
            $this->sqlLog($sql, $param);
            if(!$r){
                $this->userErrorLog($sql, $param);
            }
            return $r;
        }else{
            $this->sqlLog($sql, $param);
            $r = $query->errorCode() === '00000';
            if(!$r){
                $this->userErrorLog($sql, $param);
            }
            return $r;
        }
    }
    
    /**
     * 获取某一行的指定列
     */
    private function fetchColumn( $sql, $param = array() ){
        $this->writeLog = false;
        $query = $this->query( $sql, $param, TRUE );
        $this->writeLog = true;
        if( $query->rowCount() ) {
            $r = $query->fetchColumn();
            $this->sqlLog($sql, $param);
            return $r;
        }else {
            $this->sqlLog($sql, $param);
            return FALSE;
        }
    }

    /**
     * 获取单行数据
     */
    private function getRecord( $sql, $param = array() ) {
        $this->writeLog = false;
        $sql .= " LIMIT 1";
        $query = $this->query($sql, $param, TRUE);
        $this->writeLog = true;
        if($query->rowCount()){
            $r = $query->fetch(\PDO::FETCH_ASSOC);
            $this->sqlLog($sql, $param);
            return $r;
        }else {
            $this->sqlLog($sql, $param);
            return array();
        }
    }
    
    /**
     * 获取多行数据
     */
    private function getRecords( $sql, $param = array() ) {
        $this->writeLog = false;
        $query = $this->query($sql, $param, TRUE);
        $this->writeLog = true;
        if($query->rowCount()) {
            $r = $query->fetchAll(\PDO::FETCH_ASSOC);
            $this->sqlLog($sql, $param);
            return $r;
        }else {
            $this->sqlLog($sql, $param);
            return array();
        }
    }
    
    /**
     * 获取最后插入数据ID
     */
    private function getLastInsertId(){
        return $this->dbh->lastInsertId();
    }
          
    /**
     * 记录用户级别的错误日志
     */
    private function userErrorLog($sql ,$param = array()){
    	$logText = date('Y-m-d H:i:s') . " | Host:{$this->dbHost} | Port:{$this->dbPort} | DbName:{$this->dbName} | SQL:". $this->formatLogSql($sql, $param)."\r\n";
        return File::add($logText, 'user_sql_error_log');
    }
    
    /**
     * 记录系统级别的错误日志
     */
    private function errLog($msg, $param = array()){
    	$logText = date('Y-m-d H:i:s') . " | Host:{$this->dbHost} | Port:{$this->dbPort} | DbName:{$this->dbName} | ErrorMsg:" . $msg;
        if($param){
            $logText .= "|Param:".str_replace(array("\n"," "),"",var_export($param, TRUE));
        }
        $logText .= "\r\n";
        return File::add($logText, 'sql_error_log');
    }
    
    /**
     * 执行sql记录日志
     */
    private function sqlLog($sql, $param = array()) {
        $logText = date('Y-m-d H:i:s') . " | Host:{$this->dbHost} | Port:{$this->dbPort} | DbName:{$this->dbName} | Response Time:".($this->getMicrotime()-$this->startTime)." | SQL:" . $this->formatLogSql($sql, $param)."\r\n";
        return File::add($logText, 'sql_log');
    }
    
     /**
     * 格式化执行的SQL语句给表名加上前缀
     */
    private function formatSql($sql){
        return preg_replace_callback('/\{([a-zA-Z0-9_]*)\}/',function($matches){
            if(strtolower($matches[1]) == 'table' ){
                return $this->tableName;
            }else{
                return $this->tablePrefix($matches[1]);
            }
        }, $sql);
    }
    
    /**
     * 获取带前缀的表名
     */
    private function tablePrefix($tableName){
        if($this->tablePrefix){
            return $this->tablePrefix.$this->tableSeparator.$tableName;
        }else{
            return $tableName;
        }
    }
    
    /**
     * 格式化输出日志sql
     */
    private function formatLogSql($sql, $param = array()){
        if($param && is_array($param)){
            $keys = array();
            $values = array();
            foreach ($param as $k => $v){
                $keys[] = $k;
                $values[] = "'$v'";
            }
            return str_replace($keys, $values, $sql).';';
        }else{
            return $sql;
        }
    }
    
    /**
     * 获取当前系统的微秒数
     */
    private function getMicrotime(){
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }
}