<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Mixin;

defined('_JEXEC') or die;

trait FilterDateTime
{
	protected function filterDateTime(?string $date): string
	{
		if (empty(trim($date ?? '')))
		{
			return '';
		}

		$date = trim($date);

		if (!preg_match('/^\d{1,4}(\/|-)\d{1,2}(\/|-)\d{2,4}([[:space:]]|T){0,}(\d{1,2}:\d{1,2}(:\d{1,2}){0,1}){0,1}(\.\d{1,6}){0,1}((\+|-)\d\d:\d\d)$/', $date))
		{
			return '';
		}

		return $date;
	}

	protected function filterDate(?string $date): string
	{
		if (empty(trim($date ?? '')))
		{
			return '';
		}

		$date = trim($date);

		if (!preg_match('/^\d{1,4}-\d{1,2}-\d{2,4}$/', $date))
		{
			return '';
		}

		return $date;
	}

}