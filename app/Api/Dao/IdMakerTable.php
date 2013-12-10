<?php
namespace App\Api\Dao;
use Lavender\Dao\Exception;

class IdMakerTable extends \Lavender\Dao\Table
{
	protected $database = 'id_maker';
	protected $table = 'xx_id_maker';

	public function make($name, $num, $start)
	{
		//escape
		$name = $this->db_handle->escape($name);
		$num = intval($num);
		if ($num < 1) {
			$num = 1;
		}

		$start = intval($start);
		$now = time();

		//start
		$this->db_handle->start_transaction();

		//update
		$sql = "INSERT INTO {$this->table}(name,num,created,updated) VALUES('{$name}',{$start}+{$num},{$now},{$now}) ON DUPLICATE KEY UPDATE num=num+{$num},updated={$now}";
		$this->db_handle->execute($sql);

		//get in transaction
		$records = $this->db_handle->get($this->table, "name='{$name}'", array('num') );

		//commit
		$this->db_handle->commit();

		return $records[0]['num'] - $num;
	}
}
