<?php
$default_id_maker = array(
	'maker' => '\App\Api\Dao\IdMakerTable',
	'name' => 'default',
	'start' => '1000000',
);

return array(
	'account' => $default_id_maker,
	'post' => $default_id_maker,
	'picture' => array(
		'maker' => '\App\Api\Dao\IdMakerTable',
		'name' => 'picture',
		'start' => '1000000',
	),
	'invite' => array(
		'maker' => '\App\Api\Dao\IdMakerTable',
		'name' => 'invite',
		'start' => '1000000',
	),
);
