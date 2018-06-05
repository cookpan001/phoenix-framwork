<?php

define('TEST_NAEMSPACE', 'App');
define('TEST_NAEMSPACE_LEN', strlen(TEST_NAEMSPACE));
define('CONF_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'conf'. DIRECTORY_SEPARATOR);
error_reporting(E_ALL);
ini_set('display_errors', 'On');
function test_autoloader($class) {
    if(substr($class, 0, TEST_NAEMSPACE_LEN) != TEST_NAEMSPACE){
        return false;
    }
    $filepath = __DIR__ . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR .str_replace('\\', DIRECTORY_SEPARATOR, substr($class, TEST_NAEMSPACE_LEN + 1)). '.php';
    if(file_exists($filepath)){
        require_once $filepath;
        return true;
    }
    return false;
}
function test_autoloader2($class) {
    if(substr($class, 0, 7) != 'Phoenix'){
        return false;
    }
    $filepath = dirname(__DIR__) . DIRECTORY_SEPARATOR. 'src'. DIRECTORY_SEPARATOR .str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 7 + 1)). '.php';
    if(file_exists($filepath)){
        require_once $filepath;
        return true;
    }
    return false;
}
spl_autoload_register('test_autoloader');
spl_autoload_register('test_autoloader2');