<?php
namespace Phoenix\Framework\Base;

class Pdo
{
    use GenSql;
    
    private $pass;
    private $user;
    /**
     *
     * @var \PDO
     */
    private $link = null;
    private $host = '';
    private $port = '';
    private $db = '';
    private $affected_rows = 0;
    
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
        $options = array(
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
            \PDO::ATTR_PERSISTENT => true,
        );
        try{
            $this->link = new \PDO("mysql:dbname=$this->db;host=$this->host;port=$this->port;charset=utf8", $this->user, $this->pass, $options);
        } catch (\Exception $ex) {
            Log::sql($this->host.':'.$this->port, $ex->getMessage());
            return null;
        }
        return 0;
    }
    
    public function changeDb($dbName)
    {
        if($this->db == $dbName){
            return true;
        }
        $ret = $this->link->query("USE $dbName");
        $this->db = $dbName;
        return $ret;
    }
    
    public function escape($v)
    {
        if(is_int($v)){
            return $v;
        }
        return $this->link->quote($v);
    }
    
    public function query($sql, $id = '', $overwrite = null, $value = '')
    {
        $t1 = microtime(true);
        $ret = $this->link->query($sql);
        $t2 = microtime(true);
        $errno = 0;
        $error = '';
        if('00000' !== $this->link->errorCode()){
            list(, $errno, $error) = $this->link->errorInfo();
        }
        if(defined('LOG_ON_ERROR') && LOG_ON_ERROR){
            if($errno){
                Log::sql('pdo:'.$this->host.':'.$this->port, $this->db, $sql, $error, $errno, ($t2 - $t1) * 1000);
            }
        }else{
            Log::sql('pdo:'.$this->host.':'.$this->port, $this->db, $sql, $error, $errno, ($t2 - $t1) * 1000);
        }
        if($errno == 2006 //MySQL server has gone away
        || $errno == 2013 //Lost connection to MySQL server during query
        || $errno == 2048 //Invalid connection handle
        || $errno == 2055)//Lost connection to MySQL server at '%s', system error: %d
        {
            $this->link = null;
            $this->connect();
            $ret = $this->link->query($sql);
            list(, $errno, $error) = $this->link->errorInfo();
        }
        if($errno){
            throw new \Exception("PDO Exception: {$errno}:{$error}" . "\nSQL:{$sql}\n");
        }
        return $this->parseResult($ret, $id, $value, $overwrite);
    }
    
    public function multiQuery($sql, $id = '', $overwrite = null, $value = '')
    {
        if(!is_array($sql)){
            return $this->query($sql, $id, $overwrite, $value);
        }
        if(count($sql) == 1){
            return $this->query($sql[0], $id, $overwrite, $value);
        }
        $arr = [];
        foreach($sql as $str){
            $tmp = $this->query($str, $id, $overwrite, $value);
            if(isset($tmp[0])){
                $arr = array_merge($arr, $tmp);
            }else{
                $arr += $tmp;
            }
        }
        return $arr;
    }
    
    public function prepare($sql, $id = '', $overwrite = null, $value = '')
    {
        return $this->link->prepare($sql, $id, $overwrite, $value);
    }
    
    public function exec($sql)
    {
        $t1 = microtime(true);
        $ret = $this->link->exec($sql);
        $t2 = microtime(true);
        if(defined('PHOENIX_LOG_ON_ERROR') && PHOENIX_LOG_ON_ERROR){
            if('00000' !== $this->link->errorCode()){
                Log::sql($this->host.':'.$this->port, $this->db, $sql, implode(' ', $this->link->errorInfo()), ($t2 - $t1) * 1000);
            }
        }else{
            Log::sql($this->host.':'.$this->port, $this->db, $sql, implode(' ', $this->link->errorInfo()), ($t2 - $t1) * 1000);
        }
        if('00000' !== $this->link->errorCode()){
            throw new \Exception('PDO Exception: '.implode(' ', $this->link->errorInfo) . "\nSQL:{$sql}\n");
        }
        $this->affected_rows = $ret;
        return $ret;
    }
    
    private function parseResult($ret, $id, $value, $overwrite)
    {
        $result = array();
        while($row = $ret->fetch(\PDO::FETCH_ASSOC)){
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
        return $result;
    }

    public function affectedRow()
    {
        return $this->affected_rows;
    }
    
    public function insertId()
    {
        return $this->link->lastInsertId();
    }
    
    public function transaction($func)
    {
        $this->link->beginTransaction();
        if($func($this)){
            $this->link->commit();
        }else{
            $this->link->rollback();
        }
    }
    
    public function __destruct()
    {
        if(!empty($this->link)){
            $this->link = null;
        }
    }
}