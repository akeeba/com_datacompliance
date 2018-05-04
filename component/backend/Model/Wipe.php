<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Model;

defined('_JEXEC') or die;

use Akeeba\DataCompliance\Admin\Helper\Export as ExportHelper;
use FOF30\Model\DataModel\Exception\RecordNotLoaded;
use FOF30\Model\Model;
use RuntimeException;
use SimpleXMLElement;

/**
 * A model to wipe users (right to be forgotten)
 */
class Wipe extends Model
{
	protected $error = '';

	/**
	 * Wipes the user information. If it returns FALSE use getError to retrieve the reason.
	 *
	 * @param   int $userId The user ID to export
	 *
	 * @return  bool  True on success.
	 *
	 * @throws  RuntimeException  If wipe is not possible
	 */
	public function wipe($userId, string $type = 'user'): bool
	{
		if (!$this->checkWipeAbility($userId))
		{
			return false;
		}

		/** @var Wipetrails $auditRecord */
		$auditRecord = $this->container->factory->model('Wipetrails')->tmpInstance();

		// Do I have an existing data wipe record?
		try
		{
			$auditRecord->findOrFail(['user_id' => $userId]);

			$isDebug     = defined('JDEBUG') && JDEBUG;
			$platform    = $this->container->platform;
			$isSuperUser = $platform->getUser()->authorise('core.admin');
			$isCli       = $platform->isCli();

			if (!($isDebug && ($isCli || $isSuperUser)))
			{
				throw new RuntimeException(\JText::_('COM_DATACOMPLIANCE_WIPE_ERR_TRAILEXISTS'));
			}

			$auditRecord->type = $type;
		}
		catch (RecordNotLoaded $e)
		{
			$auditRecord->create([
				'user_id' => $userId,
				'type'    => $type,
			]);
		}

		// Actually delete the records
		$this->importPlugin('datacompliance');

		$auditItems = [];
		$results    = $this->runPlugins('onDataComplianceDeleteUser', [$userId, $type]);

		foreach ($results as $result)
		{
			if (!is_array($result))
			{
				continue;
			}

			$auditItems = array_merge($auditItems, $result);
		}

		// Update audit record with $auditItems
		$auditRecord->items = $auditItems;
		$auditRecord->save();

		return true;
	}

	/**
	 * Checks if we can wipe a user. If it returns FALSE use getError to retrieve the reason.
	 *
	 * @param   int $userId
	 *
	 * @return  bool  True if we can wipe the user
	 */
	public function checkWipeAbility($userId): bool
	{
		$this->importPlugin('datacompliance');

		try
		{
			$this->runPlugins('onDataComplianceCanDelete', [$userId]);
		}
		catch (RuntimeException $e)
		{
			$this->error = $e->getMessage();

			return false;
		}

		return true;
	}

	/**
	 * Get the user IDs which are to be deleted for lifecycle management reasons
	 *
	 * @return  array
	 */
	public function getLifecycleUserIDs(): array
	{
		// Load the plugins.
		$this->importPlugin('datacompliance');

		try
		{
			// Run the plugin events to get lifecycle user records
			$jNow     = $this->container->platform->getDate();
			$results  = $this->runPlugins('onDataComplianceGetEOLRecords', [$jNow]);
		}
		catch (RuntimeException $e)
		{
			$this->error = $e->getMessage();

			return [];
		}

		// Merge the plugin results and make sure we do not have any duplicated records
		$ret = [];

		foreach ($results as $result)
		{
			if (!is_array($result))
			{
				continue;
			}

			$ret = array_merge($ret, $result);
			$ret = array_unique($ret);
		}

		return $ret;
	}

	/**
	 * Get the reason why wiping is not allowed
	 *
	 * @return  string
	 */
	public function getError(): string
	{
		return $this->error;
	}

	/**
	 * Load plugins of a specific type. Do not go through FOF; it does not run that under CLI.
	 *
	 * @param   string $type The type of the plugins to be loaded
	 *
	 * @return void
	 */
	public function importPlugin($type)
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
	public function runPlugins($event, $data)
	{
		if (class_exists('JEventDispatcher'))
		{
			return \JEventDispatcher::getInstance()->trigger($event, $data);
		}

		return \JFactory::getApplication()->triggerEvent($event, $data);
	}
}