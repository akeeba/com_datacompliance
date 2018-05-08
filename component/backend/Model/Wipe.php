<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Model;

defined('_JEXEC') or die;

use Akeeba\DataCompliance\Admin\Helper\Export as ExportHelper;
use DateTime;
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
	 * @param   int     $userId  The user ID to export
	 * @param   string  $type    user, admin or lifecycle
	 *
	 * @return  bool  True on success.
	 *
	 * @throws  RuntimeException  If wipe is not possible
	 * @throws \Exception
	 */
	public function wipe($userId, string $type = 'user'): bool
	{
		if (!$this->checkWipeAbility($userId, $type))
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

		// Set a session variable indicating we are wiping a profile, therefore no change audit trail should be preserved
		$this->container->platform->setSessionVar('wiping', true, 'com_datacompliance');

		// Run the user account removal
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

		// Unset the session variable indicating we are wiping a profile
		$this->container->platform->setSessionVar('wiping', false, 'com_datacompliance');

		return true;
	}

	/**
	 * Checks if we can wipe a user. If it returns FALSE use getError to retrieve the reason.
	 *
	 * @param   int       $userId  The user ID we are asked for permission to delete
	 * @param   string    $type    user, admin or lifecycle
	 * @param   DateTime  $when    When is the deletion going to take place? Leaving null means "right now"
	 *
	 * @return  bool  True if we can wipe the user
	 *
	 * @throws  \Exception
	 */
	public function checkWipeAbility(int $userId, string $type = 'user', DateTime $when = null): bool
	{
		$this->importPlugin('datacompliance');

		try
		{
			$this->runPlugins('onDataComplianceCanDelete', [$userId, $type, $when]);
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
	 * @param   bool  $onlyNonWiped  If true, only return user IDs whose accounts have NOT been already wiped.
	 *
	 * @return  array
	 */
	public function getLifecycleUserIDs(bool $onlyNonWiped = true): array
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

		// Remove user IDs already wiped from the previous list?
		if ($onlyNonWiped)
		{
			$alreadyWiped = $this->getWipedUserIDs();

			$ret = array_diff($ret, $alreadyWiped);
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

	/**
	 * Get the IDs of the users we have already deleted
	 *
	 * @return  array
	 */
	public function getWipedUserIDs(): array
	{
		$db           = $this->container->db;
		$query        = $db->getQuery(true)
			->select('user_id')
			->from($db->qn('#__datacompliance_wipetrails'))
			->group($db->qn('user_id'));
		$alreadyWiped = $db->setQuery($query)->loadColumn(0);

		return $alreadyWiped;
	}
}