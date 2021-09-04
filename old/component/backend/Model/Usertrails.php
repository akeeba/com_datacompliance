<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Model;

defined('_JEXEC') or die;

use Akeeba\DataCompliance\Admin\Model\Mixin\FilterByUser;
use FOF40\Container\Container;
use FOF40\Model\DataModel;
use FOF40\IP\IPHelper as Ip;
use JDatabaseQuery;

/**
 * User profile changes audit trails
 *
 * @property int    datacompliance_usertrail_id   Primary key.
 * @property int    user_id                       User ID whose information changed.
 * @property string created_on                    When the changes were made.
 * @property int    created_by                    Who initiated the changes (if it's 0 then it's a system / CLI
 *           change).
 * @property string requester_ip                  The IP of the person who performed the change.
 * @property array  items                         The changes made. The content of some changes is redacted for
 *           security reasons.
 */
class Usertrails extends DataModel
{
	use FilterByUser;

	/** @inheritDoc */
	public function __construct(Container $container, array $config = [])
	{
		parent::__construct($container, $config);

		$this->filterByUserField       = 'user_id';
		$this->filterByUserSearchField = 'user_id';
	}

	/** @inheritDoc */
	public function check()
	{
		if (empty($this->user_id))
		{
			throw new \RuntimeException("User change audit trail: cannot have an empty user ID");
		}

		if (empty($this->requester_ip))
		{
			if ($this->container->platform->isCli())
			{
				$this->requester_ip = '(CLI)';
			}
			else
			{
				$this->requester_ip = Ip::getIp();
			}
		}

		if (empty($this->items))
		{
			$this->items = [];
		}

		/** @var self $static This docblock is to keep phpStorm's static analysis from complaining */
		$static = parent::check();

		return $static;
	}

	/**
	 * Convert the items property (an array) into a JSON-encoded string
	 *
	 * @param   array  $value  The value to encode
	 *
	 * @return  string
	 */
	protected function setItemsAttribute($value)
	{
		return $this->setAttributeForImplodedArray($value);
	}

	/**
	 * Converts the JSON-encoded items property back into a PHP array
	 *
	 * @param   string  $value  The JSON-encoded string
	 *
	 * @return  array  The converted PHP array
	 */
	protected function getItemsAttribute($value)
	{
		return $this->getAttributeForImplodedArray($value);
	}

	/**
	 * Converts the loaded comma-separated list into an array
	 *
	 * @param   string  $value  The comma-separated list
	 *
	 * @return  array  The exploded array
	 */
	protected function getAttributeForImplodedArray($value)
	{
		if (is_array($value))
		{
			return $value;
		}

		if (empty($value))
		{
			return [];
		}

		$value = json_decode($value, true);

		if (empty($value))
		{
			$value = [];
		}

		return $value;
	}

	/**
	 * Converts an array of values into a comma separated list
	 *
	 * @param   array|string  $value  The array of values
	 *
	 * @return  string  The imploded comma-separated list
	 */
	protected function setAttributeForImplodedArray($value)
	{
		if (!is_array($value))
		{
			return $value;
		}

		$value = json_encode($value);

		return $value;
	}

	/**
	 * Executes before FOF builds the select query to retrieve model records
	 *
	 * @param   JDatabaseQuery  $query  The query object to modify
	 *
	 * @return  void
	 */
	protected function onBeforeBuildQuery(JDatabaseQuery &$query): void
	{
		// Apply filtering by user. This is a relation filter, it needs to go before the main query builder fires.
		$this->filterByUser($query);
	}
}