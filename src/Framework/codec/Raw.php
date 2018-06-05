<?php
namespace Phoenix\Framework\Codec;

use Phoenix\Framework\Base\Codec;

class Raw implements Codec
{
    const END = "\r\n";
    const TAB = "\t";
    
    public function serialize($data)
    {
        $tmp = implode(self::TAB, $data) . self::END;
        return pack('N', strlen($tmp)).$tmp;
    }

    public function unserialize($data)
    {
        $ret = array();
        while(strlen($data)){
            $arr = unpack('N', substr($data, 0, 4));
            $strlen = array_pop($arr);
            $ret[] = explode(self::TAB, substr($data, 4, $strlen));
            $data = substr($data, 4 + $strlen);
        }
        return $ret;
    }
    
    public function encode(...$data)
    {
        return $this->serialize($data);
    }
}