<?php
namespace Phoenix\Framework\Route;

class Dispatcher
{
    private $basePath = '';
    private static $instance = null;
    private $cache = array();
    private $register = array();

    private function __construct($basepath)
    {
        if ('' == $basepath) {
            if(defined('PHOENIX_WORK_ROOT')){
                $this->basePath = PHOENIX_WORK_ROOT;
            }else{
                $this->basePath = dirname(dirname(dirname(dirname(dirname(__DIR__))))) . DIRECTORY_SEPARATOR;
            }
        } else {
            $this->basePath = $basepath;
        }
    }
    /**
     * @return \self
     */
    public static function getInstance($basepath = '')
    {
        if (is_null(self::$instance)) {
            self::$instance = new self($basepath);
        }
        return self::$instance;
    }
    
    public function setUriObj($obj)
    {
        if(empty($this->register)){
            $this->register = $obj;
        }
    }
    
    public function hasUri($uri)
    {
        if(is_array($this->register)){
            return isset($this->register[$uri]) ? true : false;
        }
        if(class_exists('\Swoole\Table') && $this->register instanceof \Swoole\Table){
            $str = $this->register->get($uri);
            if($str){
                return true;
            }
        }
        return false;
    }
    
    public function getUri($uri)
    {
        if(is_array($this->register)){
            return isset($this->register[$uri]) ? $this->register[$uri] : '';
        }
        if(class_exists('\Swoole\Table') && $this->register instanceof \Swoole\Table){
            $arr = $this->register->get($uri);
            if($arr){
                return json_decode($arr['detail'], true);
            }
        }
        return null;
    }
    
    /**
     * 解析Controller目录下的有效的路由
     * @return type
     */
    public function initUri()
    {
        $handle = opendir(PHOENIX_CONTROLLER_DIR);
        if(!$handle){
            return [];
        }
        $this->register = [];
        $prefix = 'App\Controller\\';
        $tmp = array();
        while(false !== ($entry = readdir($handle))){
            if('.' == $entry || '..' == $entry){
                continue;
            }
            if(is_file(PHOENIX_CONTROLLER_DIR. $entry)){
                $uri = strtolower(str_replace(['Controller', '.php'], ['', ''], $entry));
                $classname = $prefix . substr($entry, 0, -4);
                $tmp[$uri] = $classname;
                continue;
            }
            $files = scandir(PHOENIX_CONTROLLER_DIR. $entry);
            foreach($files as $file){
                if('.' == $file || '..' == $file){
                    continue;
                }
                $uri = strtolower($entry . '/' . str_replace(['Controller', '.php'], ['', ''], $file));
                $classname = $prefix . $entry . '\\' . substr($file, 0, -4);
                $tmp[$uri] = $classname;
            }
        }
        closedir($handle);
        foreach($tmp as $uri => $classname){
            if(!class_exists($classname)){
                continue;
            }
            $reflection = new \ReflectionClass($classname);
            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
            $middleWares = defined("$classname::MIDDLEWARE") ? $classname::MIDDLEWARE : [];
            $midArr = [];
            foreach($middleWares as $midClass => $expected){
                if(!class_exists($midClass)){
                    continue;
                }
                if(!is_subclass_of($midClass, '\Phoenix\Framework\Base\Middleware')){
                    continue;
                }
                $midArr[$midClass] = $expected;
            }
            foreach ($methods as $method) {
                if($method->name == 'run'){
                    $this->register[$uri] = [$classname, 'run', $midArr];
                    continue;
                }
                $methodName = str_replace('Action', '', $method->name);
                if($methodName == $method->name){
                    continue;
                }
                $this->register[$uri . '/' . strtolower($methodName)] = [$classname, $method->name, $midArr];
            }
        }
        if(isset($this->register['index'])){
            $this->register[''] = $this->register['index'];
        }
        return $this->register;
    }
    
    public function special($name, $arr)
    {
        array_shift($arr);
        $suffix = ucwords(str_replace('_', '\\', $name), '\\');
        $classname = '\App\Web\\'. $suffix;
        if(!class_exists($classname)){
            $classname = '\Phoenix\Web\\'. $suffix;
        } 
        $classname::execute(...$arr);
    }
    
    private function getHandler(&$arr)
    {
        $className = null;
        $busi = ucfirst(array_shift($arr));
        if('' == $busi){
            $busi = 'Index';
        }
        $module = ucfirst(array_shift($arr));
        if('' == $module) {
            $className = 'App\Controller\\' . $busi . 'Controller';
        } else if (is_dir($this->basePath . 'Controller' . DIRECTORY_SEPARATOR . $busi . DIRECTORY_SEPARATOR . $module)) {
            $action = lcfirst(array_shift($arr));
            $className = 'App\Controller\\' . $busi . '\\' . $module . '\\' . $action . 'Controller';
        } else {
            $className = 'App\Controller\\' . $busi . '\\' . $module . 'Controller';
        }
        if (!class_exists($className)) {
            Response::error('NO classname found: '.$className, 404);
            return false;
        }
        $method = '';
        if (isset($arr[0])) {
            $prefix = lcfirst(ucwords($arr[0], '_'));
            $reqMethod = $prefix . ucfirst(Request::server('REQUEST_METHOD')) . 'Action';
            $AnyMethod = $prefix . 'Action';
            if (method_exists($className, $reqMethod)) {
                array_shift($arr);
                $method = $reqMethod;
            } else if (method_exists($className, $AnyMethod)) {
                array_shift($arr);
                $method = $AnyMethod;
            }
        }
        if ('' == $method) {
            $method = 'run';
            if (!method_exists($className, $method)) {
                Response::error('NO method found', 404);
                return false;
            }
        }
        return [$className, $method, $arr];
    }
    
    public function routes()
    {
        return $this->register;
    }
    
    public function proceed($uri = '')
    {
        if('' == $uri){
            $uri = trim(Request::server('REQUEST_URI'), '/');
        }
        $urlInfo = parse_url($uri);
        if(isset($urlInfo['query'])){
            $queryArr = [];
            parse_str($urlInfo['query'], $queryArr);
            foreach($queryArr as $k => $v){
                Request::get($k, $v);
            }
        }
        $arr = explode('/', trim($urlInfo['path']));
        if(isset($arr[0][0]) && '_' == $arr[0][0]){
            $this->special(substr($arr[0], 1), $arr);
            return;
        }
        if(empty($this->register)){
            $this->initUri();
        }
        $n = 0;
        $tmpUri = '';
        $handler = false;
        $pos = 0;
        while($arr && isset($arr[$n]) && $n < 3){
            if($tmpUri){
                $tmpUri .= '/' . $arr[$n];
            }else{
                $tmpUri = $arr[$n];
            }
            if(!$this->hasUri($tmpUri)){
                ++$n;
                continue;
            }
            $handler = $this->getUri($tmpUri);
            ++$n;
            $pos = $n;
        }
        if(false === $handler){
            Response::error('no route found', 404);
            return;
        }
        list($className, $method, $middleWare) = $handler;
        if(Request::type() == Request::HTTP){
            foreach($middleWare as $midClass => $expected){
                $ret = $midClass::run();
                if($expected === $ret){
                    continue;
                }
                Response::error($midClass::error(), $midClass::errorCode(), $midClass);
                return;
            }
        }
        while($pos--){
            array_shift($arr);
        }
        $className::$method(...$arr);
    }

    /**
     * 解析路由，以及分配处理类和方法
     * @return int
     */
    public function run($uri = '')
    {
        if(!empty($this->register)){
            $this->proceed($uri);
            return;
        }
        if('' == $uri){
            $uri = trim(Request::server('REQUEST_URI'), '/');
        }
        $urlInfo = parse_url($uri);
        $uri = trim($urlInfo['path']);
        if(isset($urlInfo['query'])){
            $queryArr = [];
            parse_str($urlInfo['query'], $queryArr);
            foreach($queryArr as $k => $v){
                Request::get($k, $v);
            }
        }
        $arr = explode('/', $uri);
        if(isset($arr[0][0]) && '_' == $arr[0][0]){
            $this->special(substr($arr[0], 1), $arr);
            return;
        }
        if(isset($this->cache[$uri])){
            $handler = $this->cache[$uri];
            $arr = [];
        }else{
            $handler = $this->getHandler($arr);
            if(false === $handler){
                return;
            }
            if(empty($arr)){
                $this->cache[$uri] = $handler;
            }
        }
        list($className, $method) = $handler;
        if(Request::type() == Request::HTTP){
            if(defined("$className::MIDDLEWARE") && $className::MIDDLEWARE){
                foreach($className::MIDDLEWARE as $midClass => $expected){
                    if(!class_exists($midClass)){
                        Response::error("Middleware $midClass no found", 500);
                        return;
                    }
                    if(!is_subclass_of($midClass, 'Phoenix\Framework\Base\Middleware')){
                        Response::error("Middleware $midClass did not implements Middleware interface", 500);
                        return;
                    }
                    $ret = $midClass::run();
                    if($expected !== $ret){
                        Response::error($midClass::error(), $midClass::errorCode(), $midClass);
                        return;
                    }
                }
        }
        }
        $className::$method(...$arr);
    }
}