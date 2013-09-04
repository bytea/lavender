<?php
namespace App\Action;
use \Lavender\Filter;

class Index extends \Lavender\WebPage
{
	protected $without_auth_actions = array(
		'*',
	);

	/**
	 * default action
	 *
	 * @return array
	 */
	public function index_action()
	{
		return array('code' => 0, 'msg' => 'welcome');
	}
}

