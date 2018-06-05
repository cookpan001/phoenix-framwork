<?php
namespace Phoenix\Web;

use Phoenix\Framework\Route\Request;
use Phoenix\Framework\Route\Response;
use Phoenix\Framework\Util\Helper;

class Debug
{
    private $isAjax = false;
    
    function __construct()
    {
        if(Request::request('dataType'))
        {
            $this->isAjax = true;
            call_user_func_array(array($this, Request::request('op').'Action'), array());
        }
    }
    
    public static function execute()
    {
        ob_start();
        $app = new self();
        if(!$app->isAjax()){
            include __DIR__ . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . 'debug.html';
        }
        $str = ob_get_contents();
        Response::succ($str, true);
        ob_clean();
    }
    
    public function isAjax()
    {
        return $this->isAjax;
    }
    
    function run()
    {
        if(empty(Request::request('rawOutput')))
        {
            echo '<textarea id="result">';
        }
        if (!empty(Request::request('op')))
        {
            $op = Request::request('op') . 'Action';
            if (!method_exists($this, $op))
            {
                echo "method not exists.<br/>";
                return;
            }

            $ret = call_user_func_array(array($this, $op), array());
            if (is_array($ret) || is_object($ret) || is_resource($ret))
            {
                var_export($ret);
            } else
            {
                echo $ret;
            }
        }
        if(empty(Request::request('rawOutput')))
        {
            echo '</textarea>';
        }
    }
    
    function getCode()
    {
        $historySize = 20;
        if(!isset($_SESSION['code']) || Request::request('Clear'))
        {
            $_SESSION['code'] = array();
        }
        if(!Request::request('code'))
        {
            $code = '';
            return $code;
        }
        $code = Request::request('code');
        $history = $_SESSION['code'];
        if(Request::request('historyId') && isset($history[Request::request('historyId')]))
        {
            $code = $history[Request::request('historyId')];
        }
        else
        {
            $history[time()] = $code;
        }
        if(count($history) > $historySize)
        {
            $tmp = array_chunk($history, $historySize);
            $history = array_pop($tmp);
            unset($tmp);
        }
        $_SESSION['code'] = $history;
        return $code;
    }

    function executeAction()
    {
        $code = $this->getCode();
        if(!Request::request('historyId'))
        {
            $ret = eval($code);
            var_export($ret);
        }
    }

    function getShellCode()
    {
        if(Request::request('shellCode'))
        {
            return trim(Request::request('shellCode'));
        }
        return '';
    }

    function shellAction()
    {
        $code = $this->getShellCode();
        if('' == $code)
        {
            echo "";
            return;
        }
        $q = <<<EOS
            exec(\$code,\$r);return implode("\n",\$r);
EOS;
        $ret = eval($q);
        echo $ret;
    }
    
    function timestampAction()
    {
        $timestamp = Request::request('timestamp');
        $date = Request::request('date');
        if(empty($timestamp) && empty($date)){
            echo time()."\n";
            echo date('Y-m-d H:i:s P')."\n";
        }
        if(!empty($timestamp)){
            echo date('Y-m-d H:i:s P', $timestamp)."\n";
        }
        if(!empty($date)){
            echo strtotime($date);
        }
    }
    
    function jsonDiffAction()
    {
        $content1 = trim(Request::request('content1'));
        $content2 = trim(Request::request('content2'));
        echo Helper::diffArray(json_decode($content1, true), json_decode($content2, true));
    }
    
    function base64decodeAction()
    {
        $content = trim(Request::request('content'));
        echo base64_decode($content);
    }
    
    public static function getRedisList()
    {
        $json = \Phoenix\Framework\Base\Config::getRedis();
        $str = '<select name="redisType">';
        foreach($json as $type => $_v){
            $str .= "<option value='$type'>$type</option>";
        }
        return $str.'</select>';
    }
    
    function redisQueryAction()
    {
        $redisType = Request::request('redisType');
        $key = Request::request('prefix');
        if($redisType == 'other'){
            $redis = new Predis\Client(Request::request('conn'));
        }else{
            $redis = Base_Redis::getInstance($redisType);
        }
        $type = $redis->type($key);
        $ttl = $redis->ttl($key);
        switch ($type) {
            case 'string':
                $ret = $redis->get($key);
                break;
            case 'list':
                $ret = $redis->lrange($key,0,-1);
                break;
            case 'set':
                $ret = $redis->smembers($key);
                break;
            case 'zset':
                $ret = $redis->zrangebyscore($key,'-inf','+inf','withscores');
                break;
            case 'hash':
                $ret = $redis->hgetall($key);
                break;
            default:
                $ret = '';
                break;
        }
        if(!empty(Request::request('resultType'))){
            $resultType = Request::request('resultType');
            if(is_array($ret)){
                foreach($ret as $k => $value){
                    if($type == 'zset'){
                        if(is_string($k) && strlen($k) && ord($k[0]) > 127){
                            $result[] = Helper::decode($k, $resultType);
                            $result[] = $value;
                        }else{
                            $result[] = array('value' => Helper::decode($value[0], $resultType), 'score' => $value[1]);
                        }
                    }else if(is_string($k) && strlen($k) && ord($k[0]) > 127){
                        $result[] = Helper::decode($k, $resultType);
                        $result[] = $value;
                    }else{
                        $result[$k] = Helper::decode($value, $resultType);
                    }
                }
            }else{
                $result = Helper::decode($ret, $resultType);
            }
        }else{
            $result = $ret;
        }
        echo "Key: $key\n";
        echo "Data type: $type\n";
        echo "Time to live: $ttl seconds\n";
        echo "Data: \n\n";
        var_export($result);
    }
    
    function getKeysAction()
    {
        $redisType = Request::request('redisType');
        $prefix = Request::request('prefix');
        if($redisType == 'other'){
            $redis = new Predis\Client(Request::request('conn'));
        }else{
            $redis = Base_Redis::getInstance($redisType);
        }
        $data = $redis->keys($prefix.'*');
        sort($data);
        echo json_encode($data);
    }
}