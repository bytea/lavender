<?php
date_default_timezone_set('Asia/Shanghai');
set_error_handler('error_handler');

define('L_URL_BASE', '/');
define('L_SITE_NAME', 'LAVENDER DEMO');
define('L_SITE_DOMAIN', 'lvd-demo.com');

function error_handler($errno, $errstr, $errfile, $errline)
{
	$contents = date('y-m-d H:i:s') . "\t{$errfile}:{$errline}\t{$errno}\t{$errstr}\n";
	file_put_contents(L_WORKSPACE_PATH . 'log/php.log', $contents, FILE_APPEND);
}

function using($name, $path = L_APP_PATH)
{
	$file_path = $path . $name . '.php';
	$result = include_once($file_path);
	if ($result === false) {
		throw new Exception("{$file_path} not found");
	}
}

return array(
	'root_domain' => L_SITE_DOMAIN,
	'domain' => L_SITE_DOMAIN,
	'uri_path' => '/',
	'session_timeout' => 20 * 60, //20 min
	'session_dao' => '\Lavender\Dao\SessionKvTable',
	'action_modules' => array(
		'index',
	),
);
