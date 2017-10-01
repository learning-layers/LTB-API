<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
//die('We are moving the server for technical maintenance. It will be down for a couple of minutes only');
chdir(dirname(__DIR__));

// Decline static file requests back to the PHP built-in webserver
if (php_sapi_name() === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

if (!file_exists('vendor/autoload.php')) {
    throw new RuntimeException(
        'Unable to load ZF2. Run `php composer.phar install` or define a ZF2_PATH environment variable.'
    );
}

// Set up autoloading
include 'vendor/autoload.php';

if (!defined('APPLICATION_PATH')) {
    define('APPLICATION_PATH', realpath(__DIR__ . '/../'));
}

$appConfig = include APPLICATION_PATH . '/config/application.config.php';

//As long as development is going on, not everybody needs to define an env var
//but it is safer to have the default env being production, so that in case the .htaccess is 
//damaged, not suddently debugging modus is turned on!!
define('_DEV_ENV', 'development');
define('_PROD_ENV', 'production');
define('_DEFAULT_ENV', _PROD_ENV);
define('_DEVELOP_ENV', ((getenv('APP_ENV') ? getenv('APP_ENV'): _DEFAULT_ENV) == _DEV_ENV));

if (_DEVELOP_ENV && file_exists(APPLICATION_PATH . '/config/development.config.php')) {
    $appConfig = Zend\Stdlib\ArrayUtils::merge($appConfig, include APPLICATION_PATH . '/config/development.config.php');
}

// Some OS/Web Server combinations do not glob properly for paths unless they
// are fully qualified (e.g., IBM i). The following prefixes the default glob
// path with the value of the current working directory to ensure configuration
// globbing will work cross-platform.
if (isset($appConfig['module_listener_options']['config_glob_paths'])) {
    foreach ($appConfig['module_listener_options']['config_glob_paths'] as $index => $path) {
        if (_DEVELOP_ENV){
            if (($path !== 'config/autoload/{,*.}{global,local}.php') && 
                ($path !== 'config/autoload/{,*.}{global,local}-development.php')) {
                continue;
            }
        } else {
            if (($path !== 'config/autoload/{,*.}{global,local}.php')) {
                continue;
            }
        }
        $appConfig['module_listener_options']['config_glob_paths'][$index] = getcwd() . '/' . $path;
    }
}


//To avoid problems with phonegap sending an unrecognised url "file://"
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT,GET,POST,PATCH,DELETE,OPTIONS,HEAD');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  header('Access-Control-Allow-Headers: Cache-Control, Pragma, Authorization, WWW-Authenticate, Origin, X-Requested-With, Content-Type, Content-Length, Accept');
  exit;
}
// Run the application!
Zend\Mvc\Application::init($appConfig)->run();
