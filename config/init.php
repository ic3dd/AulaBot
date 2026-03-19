<?php
/**
 * Bootstrap - Define caminhos e carrega configurações base.
 * Incluir no início dos ficheiros que precisam de BD ou config.
 */
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'config');
}
if (!defined('API_PATH')) {
    define('API_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'api');
}
