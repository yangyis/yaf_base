<?php
namespace Base\Db;
use Base\Db\Factory;
use Base\Db\Pdo;
use Base\Exception;
abstract class Table{
    protected $tableName;//表名称继承时必须给值
    protected $primaryKey;//自增主键ID必须给值
    protected $dbName;//数据库名称继承时必须给值
    protected $tablePrefix = NULL;
    protected $tableSeparator = '_';
    protected $db;
    protected static $instances = [];
    
    public static function getInstance(){
        $classObject = new static();
        $className = get_class($classObject);
        if( !isset(self::$instances[$className]) || !self::$instances[$className] instanceof $className){
            self::$instances[$className] = $classObject;
        }else{
            unset($classObject);
        }
        return self::$instances[$className];
    }

    protected function __construct(){
        if(empty($this->tableName)){
            Exception::error('The property tableName of class Base\Db\Table can not be empty!  ', 507);
        }
        if(empty($this->primaryKey)){
            Exception::error('The property primaryKey of class Base\Db\Table can not be empty!  ', 507);
        }
        if(empty($this->dbName)){
            Exception::error('The property dbName of class Base\Db\Table can not be empty!  ', 507);
        }
        Factory::setDbConfig();
        if($this->tablePrefix === NULL){
            $this->tablePrefix = Factory::$databaseConfig['prefix'];
        }
    }
    
    public function getDb(){
        if(!$this->db instanceof Pdo){
            $this->db = Factory::getDb($this->dbName, 0, FALSE);
        }
        $this->db->setTablePrefix($this->tablePrefix)->setTableSeparator($this->tableSeparator)->setTableName($this->tableName)->setPrimaryName($this->primaryKey);
        return $this->db;
    }
    
    /**
     * 根据条件获取单行记录
     */
    public function getRow($where, $order = array(), $field = array(), $param = array()){
        return $this->getDb()->getRow($where, $order, $field, $param);
    }
    
    /**
     * 获取多行记录
     */
    public function getList($where, $order = array(), $field = array(), $offset = 0, $size = 0, $param = array()){
        return $this->getDb()->getList($where, $order, $field, $offset, $size, $param);
    }
    
    /**
     * 根据条件统计行数
     */
    public function getCount($where, $param = array()){
        return $this->getDb()->getCount( $where, $param);
    }
    
    /**
     * 根据主键获取多行记录
     */
    public function get($ids, $field = array()){
        return $this->getDb()->get($ids, $field);
    }
    
    /**
     * 根据条件获取单行记录的某个字段
     */
    public function getColumn($where, $field = '', $order = array(), $param = array()){
        return $this->getDb()->getColumn($where, $field, $order, $param);
    }
    
    /**
     * 逻辑删除数据
     */
    public function remove($where, $isAffected = true , $param = array()){
        return $this->getDb()->update([ 'is_deleted' => 1, 'deleted_at' =>$this->getDateTime() ], $where, $isAffected, $param);
    }
    
    /**
     * 物理删除数据
     */
    public function delete($where, $isAffected = true, $param = array()){
        return $this->getDb()->delete( $where, $isAffected, $param);
    }
    
    /**
     * 写入单行数据
     */
    public function insert($data){
        return $this->getDb()->insert($data);
    }
    
    /**
     * 批量写入多行数据
     */
    public function insertBatch($data){
        return $this->getDb()->insertBatch($data);
    }
    
    /**
     * 批量根据主键批量更新多行数据
     */
    public function batchSave($data){
        return $this->getDb()->batchSave($data);
    }
    
    /**
     * 根据条件更新数据
     */
    public function update($data, $where, $isAffected = true , $param = array()){
        return $this->getDb()->update($data, $where, $isAffected, $param);
    }
    
    /**
     * 执行非查询的sql语句，即（更新、删除、添加）等
     * @param string $sql 要执行的sql语句
     * @param array $param 数组参数
     * @param array $isAffected 是否返回影响的行数
     * @return boolean $result 返回true 执行成功 false 执行失败
     **/
    public function execute($sql, $param = array(), $isAffected = true){
        return $this->getDb()->execute($sql, $param, $isAffected);
    }
    
    /**
     * 执行查询的sql语句，返回多行数据
     * @param string $sql 要执行的sql语句
     * @param array $param 数组参数
     * @return array $result 返回结果集数组
     **/
    public function select($sql, $param = array()){
        return $this->getDb()->select($sql, $param);
    }
    
    /**
     * 执行查询的sql语句，返回单行数据
     * @param string $sql 要执行的sql语句
     * @param array $param 数组参数
     * @return array $result 返回单行数据数组
     **/
    public function find($sql, $param = array()){
        return $this->getDb()->find($sql, $param);
    }
    
    /**
     * 执行查询的sql语句，返回单行数据
     * @param string $sql 要执行的sql语句
     * @param array $param 数组参数
     * @return string $result 返回查询结果数据
     **/
    public function findColumn($sql, $param = array()){
        return $this->getDb()->findColumn($sql, $param);
    }
    
    /**
     * 格式化获取系统当前时间
     */
    public function getDateTime($time = 0, $format = 'Y-m-d H:i:s'){
        if($time == 0 ){
            $time = time();
        }
        return date($format, $time);
    }
}