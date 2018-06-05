<?php
namespace Phoenix\Framework\Sys;

use Phoenix\Framework\Base\Log;
/**
 * 使用阻塞方式实现的redis客户端, 可靠性略差
 *
 * @author pzhu
 */
class MyRedis
{
    const TERMINATOR = "\r\n";

    private $port;
    private $host;
    private $conn;
    private $timeout = 5;
    public $debug = false;
    private $password;

    function __construct($host = 'localhost', $port = '6379', $timeout = 5, $password = '')
    {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->password = $password;
    }

    private function connect()
    {
        if ($this->conn) {
            return true;
        }
        $errno = 0;
        $errstr = '';
        $i = 0;
        while ($i < 5) {
            $this->conn = @pfsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
            if ($this->conn) {
                if($this->password){
                    $this->auth($this->password);
                }
                return $this->conn;
            }
        }
        if (!$this->conn) {
            $msg = 'Failed to connect redis server ' . $this->host . ':' . $this->port . ', error message: ' . $errstr . '.';
            Log::error($msg);
            return false;
        }
    }

    private function read()
    {
        if (!$this->conn) {
            return false;
        }
        $s = fgets($this->conn);
        if(false === $s){
            $this->conn = null;
            return false;
        }
        return $s;
    }

    private function cmdResponse()
    {
        // Read the response
        $s = $this->read();
        if (false === $s) {
            return false;
        }
        switch ($s[0]) {
            case '-' : // Error message
                throw new \Exception(substr($s, 1));
            case '+' : // Single line response
                return substr($s, 1);
            case ':' : //Integer number
                return (int) substr($s, 1);
            case '$' : //Bulk data response
                $i = (int) (substr($s, 1));
                if ($i == - 1) {
                    return null;
                }
                $buffer = '';
                if ($i == 0) {
                    $s = $this->read();
                }
                while ($i > 0) {
                    $s = $this->read();
                    $l = strlen($s);
                    $i -= $l;
                    if ($i < 0) {
                        $s = substr($s, 0, $i);
                    }
                    $buffer .= $s;
                }
                return $buffer;
            case '*' : // Multi-bulk data (a list of values)
                $i = (int) (substr($s, 1));
                if ($i == - 1) {
                    return null;
                }
                $res = array();
                for ($c = 0; $c < $i; $c ++) {
                    $res [] = $this->cmdResponse();
                }
                return $res;
            default :
                return false;
        }
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
        $response = array();
        for ($i = 0; $i < $this->pipeline_commands; $i++) {
            $response[] = $this->cmdResponse();
        }
        $this->pipeline = false;
        return $response;
    }

    private function cmd($command)
    {
        $this->connect();
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
        while ($s) {
            $i = fwrite($this->conn, $s);
            if(false === $i){
                $this->conn = null;
                return null;
            }
            if ($i == 0) {
                break;
            }
            $s = substr($s, $i);
        }
        if ($this->pipeline) {
            $this->pipeline_commands++;
            return null;
        } else {
            return $this->cmdResponse();
        }
    }

    function close()
    {
        if ($this->conn) {
            fclose($this->conn);
        }
        $this->conn = null;
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