<?php
namespace Phoenix\Framework\Sys;

/**
 * 加入了断线重连的redis客户端, 使用非阴塞方式
 *
 * @author pzhu
 */
class RedisClient
{
    const RETRY = 3;
    const SIZE = 1024;
    const TERMINATOR = "\r\n";

    private $port;
    private $host;
    private $socket = null;
    private $timeout = 5;
    private $password;
    /**
     *
     * @var \DF\Protocol\Redis 
     */
    private $protocol;

    function __construct($host = 'localhost', $port = '6379', $timeout = 5, $password = '')
    {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->password = $password;
        $this->protocol = new \DF\Protocol\Redis();
    }

    private function connect()
    {
        if($this->socket){
            return;
        }
        $errno = 0;
        $errstr = '';
        $uri = "tcp://{$this->host}:{$this->port}";
        $flag = STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT;
        $sock = stream_socket_client($uri, $errno, $errstr, $this->timeout, $flag);
        if($errno){
            $sock = stream_socket_client($uri, $errno, $errstr, $this->timeout, $flag);
        }
        if($sock){
            $this->socket = socket_import_stream($sock);
            socket_set_nonblock($this->socket);
            if($this->password){
                $this->auth($this->password);
            }
        }
    }

    private function send($str, $retry = 0)
    {
        if(!$this->socket){
            return false;
        }
        if($retry > self::RETRY){
            return false;
        }
        $n = socket_write($this->socket, $str, strlen($str));
        $errorCode = socket_last_error($this->socket);
        socket_clear_error($this->socket);
        if($n == 0 || (EPIPE == $errorCode || ECONNRESET == $errorCode)){
            $this->close();
            $this->connect();
            $ret = $this->send($str, $retry + 1);
            return $ret;
        }
        $tmp = '';
        socket_recv($this->socket, $tmp, self::SIZE, MSG_DONTWAIT);
        $errorCode = socket_last_error($this->socket);
        socket_clear_error($this->socket);
        if((0 === $errorCode && null === $tmp) || EPIPE == $errorCode || ECONNRESET == $errorCode){
            $this->close();
            $this->connect();
            $ret = $this->send($str, $retry + 1);
            return $ret;
        }
        return $tmp;
    }
    

    private function read()
    {
        if (!$this->socket) {
            return null;
        }
        $timeout = $this->timeout * 1000;
        $begin = false;
        $ret = '';
        while($timeout >= 0){
            $tmp = '';
            socket_recv($this->socket, $tmp, self::SIZE, MSG_DONTWAIT);
            $errorCode = socket_last_error($this->socket);
            socket_clear_error($this->socket);
            if((0 === $errorCode && null === $tmp) || EPIPE == $errorCode || ECONNRESET == $errorCode){
                $this->close();
                $this->connect();
                return null;
            }
            if (EAGAIN == $errorCode || EINPROGRESS == $errorCode) {
                if($begin && empty($tmp)){
                    break;
                }
                usleep(100);
                $timeout -= 100;
                continue;
            }
            if($tmp){
                $ret .= $tmp;
                $begin = true;
            }
        }
        if($timeout <= 0){
            return null;
        }
        return $ret;
    }

    private function cmdResponse($pre = '')
    {
        $str = $pre . $this->read();
        $ret = $this->protocol->unserialize($str);
        if(is_array($ret) && count($ret) == 1){
            return array_pop($ret);
        }
        return $ret;
    }

    private $pipeline = false;
    private $pipeline_commands = 0;

    function pipeline_begin()
    {
        $this->pipeline = true;
        $this->pipeline_commands = 0;
    }

    function pipeline_responses()
    {
        if($this->pipeline_commands == 1){
            return [$this->cmdResponse()];
        }
        $this->pipeline = false;
        $this->pipeline_commands = 0;
        return $this->cmdResponse();
    }

    private function cmd($command)
    {
        if(!$this->socket){
            $this->connect();
        }
        if (is_array($command)) {
            // Use unified command format
            $s = '*' . count($command) . self::TERMINATOR;
            foreach ($command as $m) {
                $s .= '$' . strlen($m) . self::TERMINATOR;
                $s .= $m . self::TERMINATOR;
            }
        } else {
            $s = $command . self::TERMINATOR;
        }
        $pre = $this->send($s);
        if(false === $pre){
            return null;
        }
        if ($this->pipeline) {
            $this->pipeline_commands++;
            return null;
        } else {
            return $this->cmdResponse($pre);
        }
    }

    function close()
    {
        if ($this->socket) {
            socket_close($this->socket);
        }
        $this->socket = null;
    }

    /**
     * close the connection
     * 
     * Ask the server to silently close the connection. 
     * 
     * @return void The connection is closed as soon as the QUIT command is received. 
     */
    function quit()
    {
        return $this->cmd('QUIT');
    }

    /**
     * Call any non-implemented function of redis using the new unified request protocol
     * @param string $name
     * @param array $params
     */
    function __call($name, $params)
    {
        array_unshift($params, strtoupper($name));
        $data = $this->cmd($params);
        return $data;
    }
}