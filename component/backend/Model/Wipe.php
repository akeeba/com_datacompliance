<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Model;

defined('_JEXEC') or die;

use DateTime;
use Exception;
use FOF30\Date\Date;
use FOF30\Model\DataModel\Exception\RecordNotLoaded;
use FOF30\Model\Model;
use RuntimeException;

/**
 * A model to wipe users (right to be forgotten)
 */
class Wipe extends Model
{
	protected $error = '';

	/** @var Wipetrails $auditRecord */
	protected $auditRecord;

	/** @var bool Should we skip the creation of an audit record (ie we're replaying an old one) */
	protected $skipAuditRecord = false;

	/**
	 * Wipes the user information. If it returns FALSE use getError to retrieve the reason.
	 *
	 * @param   int     $userId   The user ID to export
	 * @param   string  $type     user, admin or lifecycle
	 * @param   bool    $godMode  If true all checks are off. INCREDIBLY DANGEROUS! CAN EVEN REMOVE SUPER USERS.
	 *
	 * @return  bool  True on success.
	 *
	 * @throws Exception
	 */
	public function wipe($userId, $type = 'user', $godMode = false): bool
	{
		if (!$godMode && !$this->checkWipeAbility($userId, $type))
		{
			return false;
		}

		$this->createAuditRecord($userId, $type);

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

		$this->saveAuditRecord($auditItems);

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
	 * @throws  Exception
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
	 * @param   bool      $onlyNonWiped  If true, only return user IDs whose accounts have NOT been already wiped.
	 * @param   DateTime  $when          Get lifecycle IDs for which date / time?
	 *
	 * @return  array
	 *
	 * @throws Exception
	 */
	public function getLifecycleUserIDs(bool $onlyNonWiped = true, DateTime $when = null): array
	{
		// Load the plugins.
		$this->importPlugin('datacompliance');

		try
		{
			// Run the plugin events to get lifecycle user records
			$jNow    = $this->container->platform->getDate(empty($when) ? 'now' : $when);
			$results = $this->runPlugins('onDataComplianceGetEOLRecords', [$jNow]);
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
	 * Load plugins of a specific type.
	 *
	 * This is a simple shim to FOF, ensuring that plugins WILL be loaded under CLI.
	 *
	 * @param   string  $type  The type of the plugins to be loaded
	 *
	 * @return  void
	 */
	public function importPlugin($type)
	{
		if ($this->container->platform->isCli())
		{
			$this->container->platform->setAllowPluginsInCli(true);
		}

		$this->container->platform->importPlugin($type);
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
	 * @throws Exception
	 */
	public function runPlugins(string $event, array $data = [])
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

	/**
	 * Mark a user as notified a user that their account will be deleted. This method does NOT send an email or perform
	 * any other kind of notification. It only marks the user account as notified. If it returns false you MUST NOT
	 * send any emails to the user.
	 *
	 * @param   int       $userId  Which user should be notified?
	 * @param   DateTime  $when    When is their account going to be deleted?
	 *
	 * @return  bool  False if the user should NOT be notified (can't be deleted on $when or already notified)
	 *
	 * @throws Exception
	 */
	public function notifyUser(int $userId, DateTime $when): bool
	{
		// Can the user really be deleted on the date and time specified by $when?
		if (!$this->checkWipeAbility($userId, $when))
		{
			return false;
		}

		// Is the user already notified?
		if ($this->isUserNotified($userId))
		{
			return false;
		}

		// Mark the user notified
		$db = $this->container->db;

		// -- Delete old records
		$this->resetUserNotification($userId);

		// -- Yes, they have been notified
		$o = (object)[
			'user_id' => $userId,
			'profile_key' => 'datacompliance.notified',
			'profile_value' => 1
		];
		$db->insertObject('#__user_profiles', $o);

		// -- This is when we notified them on
		$o = (object)[
			'user_id' => $userId,
			'profile_key' => 'datacompliance.notified_on',
			'profile_value' => $this->container->platform->getDate()->toSql()
		];
		$db->insertObject('#__user_profiles', $o);

		// -- This is when we notified them for
		$o = (object)[
			'user_id' => $userId,
			'profile_key' => 'datacompliance.notified_for',
			'profile_value' => $this->container->platform->getDate($when->getTimestamp())->toSql()
		];
		$db->insertObject('#__user_profiles', $o);

		return true;
	}

	/**
	 * Reset the status of the notifications we have sent to the user regarding their profile deletion.
	 *
	 * @param   int  $userId
	 */
	public function resetUserNotification($userId)
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->delete($db->qn('#__user_profiles'))
			->where($db->qn('user_id') . ' = ' . $userId)
			->where($db->qn('profile_key') . ' LIKE ' . $db->q('datacompliance.notified%'));
		$db->setQuery($query)->execute();
	}

	/**
	 * Is the user already notified for their account deletion?
	 *
	 * If $when is specified and datacompliance.notified_for is AFTER the specified date we return false. The idea is
	 * that the user was told their account will be deleted on a date in the future, so we should NOT delete their
	 * account while they think they can take an action to prevent deletion of their account.
	 *
	 * @param   int   $userId  The user to check
	 * @param   Date  $when    When the account deletion takes place.
	 *
	 * @return  bool  True if they are already notified.
	 */
	public function isUserNotified(int $userId, $when = null): bool
	{
		$db     = $this->container->db;
		$query  = $db->getQuery(true)
			->select([
				$db->qn('profile_key'),
				$db->qn('profile_value'),
			])->from($db->qn('#__user_profiles'))
			->where($db->qn('user_id') . ' = ' . $userId)
			->where($db->qn('profile_key') . ' LIKE ' . $db->q('datacompliance.notified%'));
		$fields = $db->setQuery($query)->loadObjectList('profile_key');

		// Not notified at all
		if (!isset($fields['datacompliance.notified']) || $fields['datacompliance.notified']->profile_value != 1)
		{
			return false;
		}

		// Don't care about when they are notified?
		if (empty($when))
		{
			return true;
		}

		// We have not recorded when they are told their account expires, therefore we consider them notified.
		if (!isset($fields['datacompliance.notified_for']))
		{
			return true;
		}

		$notifiedFor = new DateTime($fields['datacompliance.notified_for']->profile_value);

		/**
		 * If the user was told their account will be deleted after the current deletion date ($when) consider them
		 * not notified. This prevents deletion of a user account before the date the user knows they can no longer
		 * take any action to prevent account deletion.
         */
		if ($notifiedFor->getTimestamp() <= $when->getTimestamp())
		{
			return true;
		}

		return false;
	}

	public function skipAuditRecord(bool $value)
	{
		$this->skipAuditRecord = $value;
	}

	/**
	 * Creates (if requested) an audit record for current operation
	 *
	 * @param   int     $userId   The user ID to export
	 * @param   string  $type     user, admin or lifecycle
	 */
	private function createAuditRecord($userId, $type)
	{
		// Always nuke current audit record instance
		$this->auditRecord = null;

		// Am I asked to ignore creating an audit record? Let's stop here
		if ($this->skipAuditRecord)
		{
			return;
		}

		$this->auditRecord = $this->container->factory->model('Wipetrails')->tmpInstance();

		// Do I have an existing data wipe record?
		try
		{
			$this->auditRecord->findOrFail(['user_id' => $userId]);

			$isDebug     = defined('JDEBUG') && JDEBUG;
			$platform    = $this->container->platform;
			$isSuperUser = $platform->getUser()->authorise('core.admin');
			$isCli       = $platform->isCli();

			if (!($isDebug && ($isCli || $isSuperUser)))
			{
				throw new RuntimeException(\JText::_('COM_DATACOMPLIANCE_WIPE_ERR_TRAILEXISTS'));
			}

			$this->auditRecord->type = $type;
		}
		catch (RecordNotLoaded $e)
		{
			$this->auditRecord->create([
				'user_id' => $userId,
				'type'    => $type,
			]);
		}
	}

	/**
	 * Saves current audit record with passes audit items. Nothing is performed if we're asked to skip the creation of
	 * an audit record
	 *
	 * @param $auditItems
	 */
	private function saveAuditRecord(array $auditItems)
	{
		if ($this->skipAuditRecord)
		{
			return;
		}

		// Update audit record with $auditItems
		$this->auditRecord->items = $auditItems;
		$this->auditRecord->save();
	}
}