<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Mixin;

defined('_JEXEC') or die;

trait TableColumnAliasTrait
{
	public function __get($name)
	{
		if ($this->hasField($name))
		{
			$realColumn = $this->getColumnAlias($name);

			return $this->{$realColumn};
		}

		return $this->{$name};
	}

	/**
	 * Magic setter, is aware of column aliases.
	 *
	 * This is required for using Joomla's batch processing to copy / move records of tables which do not have a catid
	 * column.
	 *
	 * @param $name
	 * @param $value
	 */
	public function __set($name, $value)
	{
		if ($this->hasField($name))
		{
			$realColumn        = $this->getColumnAlias($name);
			$this->{$realColumn} = $value;

			return;
		}

		$this->{$name} = $value;
	}


}