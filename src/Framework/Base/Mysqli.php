<?php
namespace Phoenix\Framework\Base;

class Mysqli
{
    use GenSql;
    
    private $pass;
    private $user;
    private $link = null;
    private $errno = 0;
    private $error = '';
    private $host = '';
    private $port = '';
    private $db = '';
    
    public function __construct($host, $port, $user, $pass, $db)
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->db = $db;
    }
    
    public function connect()
    {
        $retry = 3;
        while($retry > 0 && !$this->link){
            //permenant connect
            $this->link = mysqli_connect('p:'.$this->host, $this->user, $this->pass, $this->db, $this->port);
            $retry--;
            usleep(1000);
        }
        if($this->link->connect_errno){
            Log::sql($this->host.':'.$this->port, $this->link->connect_error);
            return null;
        }
        $this->link->set_charset("utf8mb4");
        return 0;
    }
    
    public function changeDb($dbName)
    {
        if($this->db == $dbName){
            return true;
        }
        $ret = $this->link->select_db($dbName);
        if(!$ret){
            $ret = $this->link->select_db($dbName);
        }
        $this->db = $dbName;
        return $ret;
    }

    public function escape($v)
    {
        if(is_int($v)){
            return $v;
        }
        return '\''.mysqli_escape_string($this->link, $v).'\'';
    }
    /**
     * 
     * @param string $sql
     * @param mixed $id 返回数据的主键，可以是数组
     * @param type $overwrite 相同值的数据是否覆盖
     * @param mixed $value   返回的字段
     * @return array
     * @throws \Exception
     */
    public function query($sql, $id = '', $overwrite = null, $value = '')
    {
        $t1 = microtime(true);
        $ret = $this->link->query($sql);
        $t2 = microtime(true);
        if(defined('LOG_ON_ERROR') && LOG_ON_ERROR){
            if($this->link->errno){
                Log::sql('mysqli:'.$this->host.':'.$this->port, $this->db, $sql, $this->link->error, $this->link->errno, ($t2 - $t1) * 1000);
            }
        }else{
            Log::sql('mysqli:'.$this->host.':'.$this->port, $this->db, $sql, $this->link->error, $this->link->errno, ($t2 - $t1) * 1000);
        }
        if($this->link->errno == 2006 //MySQL server has gone away
        || $this->link->errno == 2013 //Lost connection to MySQL server during query
        || $this->link->errno == 2048 //Invalid connection handle
        || $this->link->errno == 2055)//Lost connection to MySQL server at '%s', system error: %d
        {
            $this->link = null;
            $this->connect();
            $ret = $this->link->query($sql);
        }
        if(is_bool($ret)){
            if(false === $ret){
                throw new \Exception('mysqli: '.$this->link->errno . ": {$this->link->error}\nSQL:{$sql}\n", $this->link->errno);
            }
            return $ret;
        }
        return $this->parseResult($ret, $id, $value, $overwrite);
    }
    
    public function multiQuery($sql, $id = '', $overwrite = null, $value = '')
    {
        if(is_array($sql)){
            if(count($sql) == 1){
                $sql = array_pop($sql);
                return $this->query($sql, $id, $overwrite, $value);
            }
            $sql = implode(';', $sql);
        }
        $t1 = microtime(true);
        $ret = $this->link->multi_query($sql);
        $t2 = microtime(true);
        if(defined('LOG_ON_ERROR') && LOG_ON_ERROR){
            if($this->link->errno){
                Log::sql('mysqli:'.$this->host.':'.$this->port, $this->db, $sql, $this->link->error, $this->link->errno, ($t2 - $t1) * 1000);
            }
        }else{
            Log::sql('mysqli:'.$this->host.':'.$this->port, $this->db, $sql, $this->link->error, $this->link->errno, ($t2 - $t1) * 1000);
        }
        if($this->link->errno == 2006 //MySQL server has gone away
        || $this->link->errno == 2013 //Lost connection to MySQL server during query
        || $this->link->errno == 2048 //Invalid connection handle
        || $this->link->errno == 2055)//Lost connection to MySQL server at '%s', system error: %d
        {
            $this->link = null;
            $this->connect();
            $ret = $this->link->multi_query($sql);
        }
        if(is_bool($ret)){
            if(false === $ret){
                throw new \Exception($this->link->error . "\nSQL:{$sql}\n", $this->link->errno);
            }
        }
        $arr = array();
        do {
            $result = $this->link->store_result();
            if ($result) {
                $tmp = $this->parseResult($result, $id, $value, $overwrite);
                if(isset($tmp[0])){
                    $arr = array_merge($arr, $tmp);
                }else{
                    $arr += $tmp;
                }
            }
            if(!$this->link->more_results()){
                break;
            }
        } while ($this->link->next_result());
        return $arr;
    }
    
    private function parseResult($ret, $id, $value, $overwrite)
    {
        $m1 = microtime(true);
        $result = array();
        while($row = $ret->fetch_assoc()){
            if(!empty($value)){
                if(is_array($value)){
                    foreach($value as $v){
                        $line[$v] = $row[$v];
                    }
                }else{
                    $line = $row[$value];
                }
            }else{
                $line = $row;
            }
            if(empty($id)){
                $result[] = $line;
                continue;
            }
            if(!is_array($id)){
                if(isset($row[$id])){
                    if(is_null($overwrite) || $overwrite){
                        $result[$row[$id]] = $line;
                    }else{
                        $result[$row[$id]][] = $line;
                    }
                }else{
                    $result[] = $line;
                }
                continue;
            }
            $tmp = &$result;
            foreach($id as $subid){
                if(!isset($tmp[$row[$subid]])){
                    $tmp[$row[$subid]] = array();
                }
                $tmp = &$tmp[$row[$subid]];
            }
            if($overwrite){
                $tmp = $line;
            }else{
                $tmp[] = $line;
            }
            unset($tmp);
        }
        $ret->free();
        return $result;
    }

    public function affectedRow()
    {
        
        return $this->link->affected_rows;
    }
    
    public function insertId()
    {
        return $this->link->insert_id;
    }
    
    public function transaction($func)
    {
        $this->link->begin_transaction();
        if($func($this)){
            $this->link->commit();
        }else{
            $this->link->rollback();
        }
    }
    
    public function __destruct()
    {
        if(!empty($this->link)){
            $this->link->close();
            $this->link = null;
        }
    }
}
