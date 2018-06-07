<?php
namespace Phoenix\Framework\Base;

/**
 * Description of Codec
 *
 * @author cookpan001 <cookpan001@gmail.com>
 */
interface Codec
{
    public function serialize($data);
    public function unserialize($data);
}