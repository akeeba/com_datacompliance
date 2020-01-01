<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */


namespace Akeeba\DataCompliance\Admin\Model;

defined('_JEXEC') or die;

use Akeeba\DataCompliance\Admin\Model\Mixin\FilterByUser;
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
	use FilterByUser;

	public function __construct(Container $container, array $config = array())
	{
		parent::__construct($container, $config);

		$this->addBehaviour('filters');
		$this->blacklistFilters(['user_id', 'created_by']);
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

	protected function onAfterSave()
	{
		/**
		 * FOF does not call plugin events in CLI scripts. So we have to do it ourselves.
		 */
		if ($this->container->platform->isCli())
		{
			$this->importPlugin('datacompliance');
			$event = 'onComDatacomplianceModelWipetrailsAfterSave';
			$this->runPlugins($event, [$this]);
		}
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

	protected function onBeforeBuildQuery(\JDatabaseQuery &$query)
	{
		// Apply filtering by user. This is a relation filter, it needs to go before the main query builder fires.
		$this->filterByUser($query, 'user_id', 'user_id');
		$this->filterByUser($query, 'created_by', 'created_by');
	}

	/**
	 * Load plugins of a specific type. Do not go through FOF; it does not run that under CLI.
	 *
	 * @param   string $type The type of the plugins to be loaded
	 *
	 * @return void
	 */
	public function importPlugin(string $type)
	{
		\JLoader::import('joomla.plugin.helper');
		\JPluginHelper::importPlugin($type);
	}

	/**
	 * Execute plugins (system-level triggers) and fetch back an array with their return values. Do not go through FOF;
	 * it does not run that under CLI
	 *
	 * @param   string $event The event (trigger) name, e.g. onBeforeScratchMyEar
	 * @param   array  $data  A hash array of data sent to the plugins as part of the trigger
	 *
	 * @return  array  A simple array containing the results of the plugins triggered
	 *
	 * @throws \Exception
	 */
	public function runPlugins(string $event, array $data = [])
	{
		if (class_exists('JEventDispatcher'))
		{
			return \JEventDispatcher::getInstance()->trigger($event, $data);
		}

		return \JFactory::getApplication()->triggerEvent($event, $data);
	}
}