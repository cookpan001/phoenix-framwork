<?php
namespace Phoenix\Framework\Base;

/**
 * Description of Middleware
 *
 * @author zhupeng <zhupeng@davdian.com>
 */
interface Middleware
{
    /**
     * 执行中间件
     */
    public static function run(): bool;
    public static function error(): string;
    public static function errorCode(): int;
}
