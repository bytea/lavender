<?php
namespace App\Api\Dao;
use Lavender\Dao\Exception;

class UserBaseTable extends \Lavender\Dao\Table
{
	protected $database = 'user';
	protected $table = 'tb_user_base';
}
