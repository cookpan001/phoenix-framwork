<?php
namespace App\Middleware;

/**
 * Description of Auth
 *
 * @author zhupeng <zhupeng@davdian.com>
 */
class Auth implements \Phoenix\Framework\Base\Middleware
{
    public static function run(): bool
    {
        return true;
    }

    public static function error(): string
    {
        return 'Authentification Failed';
    }

    public static function errorCode(): int
    {
        return 401;
    }
}