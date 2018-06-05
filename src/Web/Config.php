<?php
namespace Phoenix\Web;

use Phoenix\Framework\Route\Response;
/**
 * Description of Config
 *
 * @author zhupeng <zhupeng@davdian.com>
 */
class Config
{
    public static function execute()
    {
        Response::succ(\Phoenix\Framework\Base\Config::getAll());
    }
}
