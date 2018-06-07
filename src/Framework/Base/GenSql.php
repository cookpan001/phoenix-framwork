<?php
namespace Phoenix\Framework\Base;

/**
 * Description of GenSql
 *
 * @author cookpan001 <cookpan001@gmail.com>
 */
trait GenSql
{
        /**
     * 
     * @param string $table : table name
     * @param array $where : where clause used in sql query
     * @param multi-array $option : 
     * id : result should be unique index by id
     * overwrite : same index should be override
     * limit : same as query sql 
     * order by: same as query sql 
     * @param array $fields : table fields
     * @return type
     */
    public function select($table, $where = array(), $option = array(), $fields = array())
    {
        $m1 = microtime(true);
        $id = isset($option['id']) ? $option['id'] : '';
        $overwrite = isset($option['overwrite']) ? $option['overwrite'] : null;
        if(empty($fields)){
            $f = '*';
        }else{
            $fids = (array)$fields;
            if($id && !in_array($id, $fids)){
                $fids = array_merge($fids, (array)$id);
            }
            $fids = array_unique($fids);
            $f = $this->quote($fids);
        }
        $sql = 'SELECT ' . $f . ' FROM ' . $table . ' ';
        $whereSql = [];
        foreach($where as $k => $v){
            if(is_int($k)){
                $whereSql[] = $v;
            }else if(is_array($v)){
                $whereSql[] = "`$k` in (" . implode(',', array_map(array($this, 'escape'), $v)) . ')';
            }else{
                $whereSql[] = "`$k`=" . $this->escape($v);
            }
        }
        if($whereSql){
            $sql .= 'WHERE '.implode(' AND ', $whereSql);
        }
        if(isset($option['groupby'])){
            if(is_array($option['groupby'])){
                $sql .= ' GROUP BY ' . implode(',', $option['groupby']);
            }else{
                $sql .= ' GROUP BY ' . $option['groupby'];
            }
        }
        if(isset($option['having'])){
            if(is_array($option['having'])){
                $sql .= ' HAVING ' . implode(',', $option['having']);
            }else{
                $sql .= ' HAVING ' . $option['having'];
            }
        }
        if(isset($option['orderby'])){
            if(is_array($option['orderby'])){
                $sql .= ' ORDER BY ' . implode(',', $option['orderby']);
            }else{
                $sql .= ' ORDER BY ' . $option['orderby'];
            }
        }
        if(isset($option['limit'])){
            $offset = isset($option['offset']) ? $option['offset'] : 0;
            $sql .= " LIMIT {$offset}," . $option['limit'];
        }
        $value = isset($option['value']) ? $option['value']: '';
        if(isset($option['multi']) && $option['multi']){
            return $sql;
        }
        return $this->query($sql, $id, $overwrite, $value);
    }
    
    private function quote($fields)
    {
        $tmp = array();
        foreach($fields as $field){
            if(ctype_alnum($field)){
                $tmp[] = "`$field`";
            }else{
                $tmp[] = $field;
            }
        }
        return implode(',', $tmp);
    }
    
    public function replace($table, $arr, $rawKey = array(), $keys = array())
    {
        if(empty($arr)){
            return false;
        }
        $sql = 'REPLACE INTO '.$table;
        $tmp = array();
        $isBatch = false;
        $raw = array_flip($rawKey);
        foreach($arr as $k => $v){
            if(is_array($v)){
                if(empty($keys)){
                    $keys = array_keys($v);
                }
                $tmpv = array();
                foreach($v as $vk => $vv){
                    $tmpv[] = isset($raw[$vk]) ? $vv : $this->escape($vv);
                }
                $tmp[] = '(' .implode(',', $tmpv).')';
                $isBatch = true;
            }else{
                if(empty($keys)){
                    $keys = array_keys($arr);
                }
                $tmp[] = isset($raw[$k]) ? $v : $this->escape($v);
            }
        }
        $sql .= '(`'.implode('`,`', $keys) .'`) VALUES ';
        $sql .= $isBatch ? implode(',', $tmp) : '('.  implode(',', $tmp) . ')';
        return $this->query($sql);
    }
    
    public function insert($table, $arr, $update = array(), $rawKey = array(), $keys = array())
    {
        if(empty($arr)){
            return false;
        }
        $raw = array_flip($rawKey);
        $ignore = empty($update);
        if($ignore){
            $sql = 'INSERT IGNORE INTO '.$table;
        }else{
            $sql = 'INSERT INTO '.$table;
        }
        $tmp = array();
        $isBatch = false;
        foreach($arr as $k => $v){
            if(is_array($v)){
                if(empty($keys)){
                    $keys = array_keys($v);
                }
                $tmpv = array();
                foreach($v as $vk => $vv){
                    $tmpv[] = isset($raw[$vk]) ? $vv : $this->escape($vv);
                }
                $tmp[] = '(' .implode(',', $tmpv).')';
                $isBatch = true;
            }else{
                if(empty($keys)){
                    $keys = array_keys($arr);
                }
                $tmp[] = isset($raw[$k]) ? $v : $this->escape($v);
            }
        }
        $sql .= '(`'.implode('`,`', $keys) .'`) VALUES ';
        $sql .= $isBatch ? implode(',', $tmp) : '('.  implode(',', $tmp) . ')';
        if(!empty($update)){
            $duplicate = array();
            foreach($update as $k => $v){
                if(isset($raw[$k])){
                    $duplicate[] = "`{$k}`={$v}";
                }else if(preg_match('#\w+\(\w+\)#U', $v)){//MYSQL func
                    $duplicate[] = "`{$k}`={$v}";
                }else{
                    $v = $this->escape($v);
                    $duplicate[] = "`{$k}`={$v}";
                }
            }
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(',', $duplicate);
        }
        return $this->query($sql);
    }
    
    public function update($table, $vals = array(), $where = array(), $rawKey = array())
    {
        if(empty($vals)){
            return 0;
        }
        $raw = array_flip($rawKey);
        $sql = "UPDATE $table SET ";
        $tmp = array();
        foreach($vals as $k => $v){
            if(is_int($k)){
                $tmp[] = "{$v}";
            }else if(isset($raw[$k])){
                $tmp[] = "`$k`=" . $v;
            }else if(preg_match('#\w+\(\.+\)#U', $v)){//MYSQL func
                $tmp[] = "`{$k}`={$v}";
            }else{
                $tmp[] = "`$k`=" . $this->escape($v);
            }
        }
        $sql .= implode(',',$tmp).' WHERE 1 ';
        foreach($where as $k => $v){
            if(is_array($v)){
                $sql .= " AND `$k` in (" . implode(',', array_map(array($this, 'escape'), $v)) . ')';
            }else if(is_int($k)){
                $sql .= ' AND '.$v;
            }else{
                $sql .= " AND `$k`=" . $this->escape($v);
            }
        }
        return $this->query($sql);
    }
    
    public function delete($table, $where = array())
    {
        if(empty($where)){
            return true;
        }
        $sql = "DELETE FROM $table WHERE 1 ";
        foreach($where as $k => $v){
            if(is_array($v)){
                $sql .= " AND `$k` in (" . implode(',', array_map(array($this, 'escape'), $v)) . ')';
            }else{
                $sql .= " AND `$k`=" . $this->escape($v);
            }
        }
        return $this->query($sql);
    }
    
    public function describe($table)
    {
        $sql = "DESC $table";
        return $this->query($sql);
    }
    
    public function tables()
    {
        $sql = "show tables";
        return $this->query($sql);
    }
    
    public function setNextId($table, $id)
    {
        $sql = "ALTER TABLE $table AUTO_INCREMENT=$id";
        return $this->query($sql);
    }
    
    public function union($table, $where = array(), $option = array(), $fields = array())
    {
        $sqls = array();
        $option['multi'] = true;
        foreach($where as $w){
            $sqls[] = '('.$this->select($table, $w, $option, $fields).')';
        }
        if(empty($sqls)){
            return array();
        }
        $id = isset($option['id']) ? $option['id'] : '';
        $overwrite = isset($option['overwrite']) ? $option['overwrite'] : null;
        $value = isset($option['value']) ? $option['value']: '';
        return $this->query(implode(' UNION ', $sqls), $id, $overwrite, $value);
    }
}
