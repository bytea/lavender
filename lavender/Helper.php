<?php
namespace Lavender;

class Helper
{
	public static function html_encode($str)
	{
		return htmlspecialchars($str, ENT_QUOTES);
	}

	public static function html_decode($str)
	{
		return htmlspecialchars_decode($str, ENT_QUOTES);
	}
}

