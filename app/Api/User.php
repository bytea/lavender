<?php
namespace App\Api;
use Lavender\Errno;
use Lavender\Exception;

class User
{
	/**
	 * single or multipie get base info
	 *
	 * @param mixed 	$id int or int array
	 *
	 * @return array
	 */
	public static function get_base($id)
	{
		if (is_array($id)) {
			$records = Dao\UserBaseTable::instance()->get($id, ['id', 'un', 'name', 'sex', 'face', 'type', 'admins', 'status', 'flags', 'intro', 'created']);

			$items = array();
			foreach ($records as $item) {
				$item['id'] = intval($item['id']);
				$item['type'] = intval($item['type']);
				$item['face'] = intval($item['face']);
				$item['sex'] = intval($item['sex']);
				$item['status'] = intval($item['status']);
				$item['flags'] = intval($item['flags']);
				$items[$item['id']] = $item;
			}

			return $items;
		}

		$result = Dao\UserBaseTable::instance()->get_single($id);

		$result['id'] = intval($result['id']);
		$result['type'] = intval($result['type']);
		$result['face'] = intval($result['face']);
		$result['sex'] = intval($result['sex']);
		$result['status'] = intval($result['status']);
		$result['flags'] = intval($result['flags']);

		return $result;
	}
}

