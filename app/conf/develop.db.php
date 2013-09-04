<?php
$main_database = array(
	'host' => '127.0.0.1',
	'port' => 3306,
	'database' => 'lvd_demo',
	'user' => 'root',
	'password' => 'root',
	'charset' => 'utf8',
);

return array(
	'session' => $main_database,
	'user' => $main_database,
);

