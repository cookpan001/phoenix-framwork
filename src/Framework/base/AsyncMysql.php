<?php
namespace Phoenix\Framework\Base;

class AsyncMysql
{
    use GenSql;
    
    private $config;
    private $client = null;
    private $affected_rows = null;
    private $insert_id = null;
    
    public function __construct($host, $port, $user, $pass, $db)
    {
        $this->config = array(
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'password' => $pass,
            'database' => $db,
            'charset' => 'utf8', //指定字符集
            'timeout' => 2,
        );
    }
    
    public function __destruct()
    {
        if($this->client && method_exists($this->client, 'close')){
            $this->client->close();
        }
    }
    
    public function connect()
    {
        $this->client = new \swoole_mysql();
        $this->client->connect($this->config);
    }
    
    function changeDb($db)
    {
        if($this->client){
            $this->client->query('USE '.$db);
        }
    }

    //TODO
    public function escape($v)
    {
        return $this->client->escape($v);
    }
    
    public function query($sql, $id = '', $overwrite = null, $value = '')
    {
        $t1 = microtime(true);
        $response = $this->client->query($sql);
        $t2 = microtime(true);
        Log::sql($this->config['host'].':'.$this->config['port'], $this->config['database'], $sql, '', intval(false === $response), ($t2 - $t1) * 1000);
        if(false === $response)//Lost connection to MySQL server at '%s', system error: %d
        {
            $this->connect();
            $response = $this->client->query($sql);
        }
        if(false === $response){
            throw new \Exception("CoroutineMysql Error: \nSQL:{$sql}\n");
        }
        return $response;
    }
    /**
     * 多条SQL查询
     * @param type $sql
     * @param type $id
     * @param type $overwrite
     * @param type $value
     * @return type
     */
    public function multiQuery($sql, $id = '', $overwrite = null, $value = '')
    {
        if(!is_array($sql)){
            return $this->query($sql, $id, $overwrite, $value);
        }
        if(count($sql) == 1){
            $sql = array_pop($sql);
            return $this->query($sql, $id, $overwrite, $value);
        }
        foreach($sql as $str){
            $this->client->query($str);
        }
        return $this->client->recv();
    }
    
    private function parseResult($ret, $id, $value, $overwrite)
    {
        $result = array();
        if(empty($ret)){
            return $result;
        }
        foreach($ret as $row){
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
        return $this->client->affected_rows;
    }
    
    public function insertId()
    {
        return $this->client->insert_id;
    }
}