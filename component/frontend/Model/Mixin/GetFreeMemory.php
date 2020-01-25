<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Site\Model\Mixin;


trait GetFreeMemory
{
	/**
	 * Get the amount of free PHP memory
	 *
	 * @return  int
	 */
	protected function getFreeMemory()
	{
		$memLimit = $this->getMemoryLimit();
		$memUsage = memory_get_usage(true);

		return $memLimit - $memUsage;
	}

	/**
	 * Get the PHP memory limit in bytes
	 *
	 * @return int  Memory limit in bytes or null if we can't figure it out.
	 */
	protected function getMemoryLimit()
	{
		static $memLimit = null;

		if (is_null($memLimit))
		{
			if (!function_exists('ini_get'))
			{
				$memLimit = 16842752;

				return $memLimit;
			}

			$memLimit = ini_get("memory_limit");
			$memLimit = $this->humanToIntegerBytes($memLimit);
		}


		return $memLimit;
	}

	/**
	 * Converts a human formatted size to integer representation of bytes,
	 * e.g. 1M to 1024768
	 *
	 * @param   string  $setting  The value in human readable format, e.g. "1M"
	 *
	 * @return  integer  The value in bytes
	 */
	protected function humanToIntegerBytes($setting)
	{
		$val = trim($setting);
		$last = strtolower($val{strlen($val) - 1});

		if (is_numeric($last))
		{
			return $setting;
		}

		$val = substr($val, 0, -1);

		switch ($last)
		{
			case 't':
				$val *= 1024;
			case 'g':
				$val *= 1024;
			case 'm':
				$val *= 1024;
			case 'k':
				$val *= 1024;
		}

		return (int) $val;
	}
}