<?php
error_reporting(E_ALL);
//ini_set("memory_limit","100M");

define('L_DEBUG', true);
define('L_ENV', 'develop'); //develop|test|work
define('L_APP_PATH', dirname(__DIR__) . '/');
define('L_WORKSPACE_PATH', dirname(L_APP_PATH) . '/');

//load & init lavender
require L_WORKSPACE_PATH . 'lavender/Core.php';

//load web options
$WEB_OPTIONS = require L_APP_PATH . 'conf/web_options.php';

\Lavender\WebPage::run($WEB_OPTIONS);

