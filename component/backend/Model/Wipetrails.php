<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */


namespace Akeeba\DataCompliance\Admin\Model;

defined('_JEXEC') or die;

use FOF30\Container\Container;
use FOF30\Model\DataModel;
use FOF30\Utils\Ip;

/**
 * Data wipe audit trails
 *
 * @property int    datacompliance_wipetrail_id   Primary key and user ID which was wiped
 * @property int    user_id                       User ID which was wiped
 * @property string type                          Data wipe type (user, admin, lifecycle)
 * @property string created_on                    When the wipe was made
 * @property int    created_by                    Who initiated the wipe (0 is CRON task or CLI user)
 * @property string requester_ip                  The IP of the person who requested the wipe
 * @property array  items                         The IDs of the items which were wiped
 */
class Wipetrails extends DataModel
{
	/**
	 * Upload the audit trail under a unique name on Amazon S3 to ensure ability to reply in case of catastrophic
	 * failure of the site and subsequent restoration from a backup.
	 */
	private function uploadToS3()
	{
		// TODO Remove this method call. Instead use a system plugin which hooks on the Model's onAfterSave event to implement the automatic upload to Amazon S3. The filename could be cached in the plugin, keyed by the user ID, to make sure we do not create double records.
	}

	/**
	 * Checks the validity of the record. Also auto-fills the created* and requester_ip fields.
	 *
	 * @return  static
	 */
	public function check()
	{
		if (empty($this->user_id))
		{
			throw new \RuntimeException("Data wipe audit trail: cannot have an empty user ID");
		}

		if (empty($this->type))
		{
			throw new \RuntimeException("Data wipe audit trail: cannot have an empty type");
		}

		if (!in_array($this->type, ['user', 'admin', 'lifecycle']))
		{
			throw new \RuntimeException("Invalid data wipe type “{$this->type}”.");
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


	protected function setItemsAttribute($value)
	{
		return $this->setAttributeForImplodedArray($value);
	}

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
			return array();
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
	 * @param   array  $value  The array of values
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
}