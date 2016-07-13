<?php
namespace Base;
use Yaf\Dispatcher;
use Yaf\Registry;
use Yaf\Controller_Abstract;
/***
 * 基础控制器,所有控制器都应该实现此类
 * @author Yangyi <yi.yang@vhall.com>
 * @create_time 2016/06/06
 * @modify_time 2016/06/15
 */
abstract class Api extends Controller_Abstract{

    /**
     * 配置文件
     *
     * @var array
     */
    public $config = [];

    /**
     * 初始化
     */
    public function init(){
        set_exception_handler([$this, 'handleException']);
        $this->config = Registry::get('config');
        $mode = $this->getParam('mode', 'json');
        if($mode == 'json'){
            $this->getResponse()->setHeader('Content-Type', 'application/json');
            $this->getResponse()->setHeader('charset', 'utf-8');
            Dispatcher::getInstance()->disableView(); 
        }else{
            Dispatcher::getInstance()->autoRender(TRUE);
            $this->getResponse()->setHeader('Content-Type', 'text/html');
            $this->getResponse()->setHeader('charset', 'utf-8');
        }
        $this->onload();
    }
    
    /**
     * 请求装载之前执行的函数
     */
    protected function onload(){
        
    }
    
    /**
     * 异常处理
     *
     * @param $e 异常
     */
    public function handleException($e){
        $this->error($e->getCode(), $e->getMessage());
    }
    
    /**
     * 输出成功
     *
     * @param array $data
     * @return bool
     */
    public function success($data = null){
        $msg  = 'success';
        $code = 200;
        $mode = $this->getParam('mode', 'json');
        if( $mode == 'json' ){
            return $this->output($code, $msg, $data);
        }else{
            $this->getView()->assign('result', $data);
        }
    }

    /**
     * 输出错误
     *
     * $this->error(Code::PASSWORD);
     *
     * @param int $code
     * @param string $msg
     * @param array $data
     * @return bool
     */
    public function error($code, $msg = 'error', $data = null){
        if($code == 200){
            $code = 500;
        }
        return $this->output($code, $msg, $data);
    }

    /**
     * 输出结果并退出
     *
     * @param int $code
     * @param string $msg
     * @param array $data
     * @return bool
     */
    public function output($code, $msg = '', $data = null){
        $result = json_encode([
            'code' => $code,
            'msg'  => $msg,
            'data' => $data,
        ]);
        $callback = $this->getParam('callback');
        if($callback){
            echo "{$callback}('{$result}');";
        }else{
            echo $result;
        }
        return true;
    }

    /**
     * 返回当前模块名
     *
     * @access protected
     * @return string
     */
    protected function getModule(){
        return $this->getRequest()->module;
    }

    /**
     * 返回当前控制器名
     *
     * @access protected
     * @return string
     */
    protected function getController(){
        return $this->getRequest()->controller;
    }

    /**
     * 返回当前动作名
     *
     * @access protected
     * @return string
     */
    protected function getAction(){
        return $this->getRequest()->action;
    }
    
    protected function getParam($param, $default=''){
        $val = $this->getRequest()->getPost($param);
        if($val === null ){
            $val = $this->getRequest()->getQuery($param);
            if($val === null){
                return $default;
            }else{
                return $val;
            }
        }else{
            return $val;
        }
    }

    /**
     * 获取GET数据
     *
     * @param string $param
     * @return mixed
     */
    protected function getQuery($param = '',$default = ''){
        if (empty($param)) {
            return $this->getRequest()->getQuery();
        }
        return $this->getRequest()->getQuery($param, $default);
    }

    /**
     * 获取POST数据
     *
     * @param string $param
     * @return mixed
     */
    protected function getPost($param = '',$default = ''){
        if (empty($param)) {
            return $this->getRequest()->getPost();
        }
        return $this->getRequest()->getPost($param,$default);
    }

    /**
     * 获取cookie数据
     *
     * @param string $param
     * @return mixed
     */
    protected function getCookie($param = ''){
        if (empty($param)) {
            return $this->getRequest()->getCookie();
        }
        return $this->getRequest()->getCookie($param);
    }

    /**
     * 获取SERVER数据
     *
     * @param string $param
     * @return mixed
     */
    protected function getServer($param = ''){
        if (empty($param)) {
            return $this->getRequest()->getServer();
        }
        return $this->getRequest()->getServer($param);
    }

    /**
     * 请求发放: GET,POST,HEAD,PUT,CLI
     *
     * @return mixed
     */
    protected function getMethod(){
        return $this->getRequest()->getMethod();
    }

    /**
     * 是否PUT操作
     *
     * @return mixed
     */
    protected function isPut(){
        return $this->getRequest()->isPut();
    }

    /**
     * 是否DELETE
     *
     * @return mixed
     */
    protected function isDelete(){
        return $this->getRequest()->getServer('REQUEST_METHOD') == 'DELETE';
    }

    /**
     * 是否GET
     *
     * @return mixed
     */
    protected function isGet(){
        return $this->getRequest()->isGet();
    }

    /**
     * 是否POST
     *
     * @return mixed
     */
    protected function isPost(){
        return $this->getRequest()->isPost();
    }

    /**
     * 是否AJAX
     *
     * @return mixed
     */
    protected function isAjax(){
        return $this->getRequest()->isXmlHttpRequest();
    }
}