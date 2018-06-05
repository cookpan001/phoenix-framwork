<?php
namespace Phoenix\Framework\Base;

/**
 * Description of Codec
 *
 * @author zhupeng <zhupeng@davdian.com>
 */
interface Codec
{
    public function serialize($data);
    public function unserialize($data);
}