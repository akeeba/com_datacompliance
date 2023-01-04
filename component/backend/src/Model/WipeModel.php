<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Akeeba\Component\DataCompliance\Administrator\Model;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Table\WipetrailsTable;
use DateTime;
use Exception;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\User\UserHelper;
use Joomla\Component\Privacy\Administrator\Removal\Status;
use Joomla\Component\Privacy\Administrator\Table\RequestTable;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;
use RuntimeException;

/**
 * A model to wipe users (right to be forgotten)
 *
 * @since  1.0.0
 */
#[\AllowDynamicProperties]
class WipeModel extends BaseDatabaseModel
{
	/** @var WipetrailsTable $auditRecord */
	protected $auditRecord;

	/** @var bool Should we skip the creation of an audit record (ie we're replaying an old one) */
	protected $skipAuditRecord = false;

	/**
	 * Checks if we can wipe a user. If it returns FALSE use getError to retrieve the reason.
	 *
	 * @param   int            $userId  The user ID we are asked for permission to delete
	 * @param   string         $type    user, admin or lifecycle
	 * @param   DateTime|null  $when    When is the deletion going to take place? Leaving null means "right now"
	 *
	 * @return  bool  True if we can wipe the user
	 *
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public function checkWipeAbility(int $userId, string $type = 'user', ?DateTime $when = null): bool
	{
		PluginHelper::importPlugin('datacompliance');
		PluginHelper::importPlugin('privacy');

		try
		{
			// Joomla Privacy
			$this->checkJoomlaPrivacyWipeAbility($userId, clone Factory::getDate($when));
			// Akeeba DataCompliance
			$this->runPlugins('onDataComplianceCanDelete', [$userId, $type, $when]);
		}
		catch (RuntimeException $e)
		{
			/** @noinspection PhpDeprecationInspection */
			$this->setError($e->getMessage());

			return false;
		}

		return true;
	}

	/**
	 * Get the user IDs which are to be deleted for lifecycle management reasons
	 *
	 * @param   bool           $onlyNonWiped  If true, only return user IDs whose accounts have NOT been already wiped.
	 * @param   DateTime|null  $when          Get lifecycle IDs for which date / time?
	 *
	 * @return  array
	 *
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public function getLifecycleUserIDs(bool $onlyNonWiped = true, ?DateTime $when = null): array
	{
		// Load the plugins.
		PluginHelper::importPlugin('datacompliance');
		PluginHelper::importPlugin('privacy');

		try
		{
			// Run the plugin events to get lifecycle user records
			$jNow    = clone Factory::getDate(empty($when) ? 'now' : $when);
			$results = $this->runPlugins('onDataComplianceGetEOLRecords', [$jNow]);
		}
		catch (RuntimeException $e)
		{
			/** @noinspection PhpDeprecationInspection */
			$this->setError($e->getMessage());

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
	 * Get the IDs of the users we have already deleted
	 *
	 * @return  array
	 * @since   1.0.0
	 */
	public function getWipedUserIDs(): array
	{
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('user_id')
			->from($db->quoteName('#__datacompliance_wipetrails'))
			->group($db->quoteName('user_id'));

		return $db->setQuery($query)->loadColumn();
	}

	/**
	 * Is the user already notified for their account deletion?
	 *
	 * If $when is specified and datacompliance.notified_for is AFTER the specified date we return false. The idea is
	 * that the user was told their account will be deleted on a date in the future, so we should NOT delete their
	 * account while they think they can take an action to prevent deletion of their account.
	 *
	 * @param   int        $userId  The user to check
	 * @param   Date|null  $when    When the account deletion takes place.
	 *
	 * @return  bool  True if they are already notified.
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public function isUserNotified(int $userId, ?Date $when = null): bool
	{
		$db     = $this->getDatabase();
		$query  = $db->getQuery(true)
			->select([
				$db->quoteName('profile_key'),
				$db->quoteName('profile_value'),
			])->from($db->quoteName('#__user_profiles'))
			->where($db->quoteName('user_id') . ' = :userId')
			->where($db->quoteName('profile_key') . ' LIKE ' .
				$db->quote('datacompliance.notified%'))
			->bind(':userId', $userId, ParameterType::INTEGER);
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
	 * @throws  Exception
	 * @since        1.0.0
	 *
	 * @noinspection PhpUnused
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
		$db = $this->getDatabase();

		// -- Delete old records
		$this->resetUserNotification($userId);

		// -- Yes, they have been notified
		$o = (object) [
			'user_id'       => $userId,
			'profile_key'   => 'datacompliance.notified',
			'profile_value' => 1,
		];
		$db->insertObject('#__user_profiles', $o);

		// -- This is when we notified them on
		$o = (object) [
			'user_id'       => $userId,
			'profile_key'   => 'datacompliance.notified_on',
			'profile_value' => (clone Factory::getDate())->toSql(),
		];
		$db->insertObject('#__user_profiles', $o);

		// -- This is when we notified them for
		$o = (object) [
			'user_id'       => $userId,
			'profile_key'   => 'datacompliance.notified_for',
			'profile_value' => (clone Factory::getDate($when->getTimestamp()))->toSql(),
		];
		$db->insertObject('#__user_profiles', $o);

		return true;
	}

	/**
	 * Reset the status of the notifications we have sent to the user regarding their profile deletion.
	 *
	 * @param   int  $userId
	 *
	 * @since   1.0.0
	 */
	public function resetUserNotification(int $userId): void
	{
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__user_profiles'))
			->where($db->quoteName('user_id') . ' = :userId')
			->where($db->quoteName('profile_key') . ' LIKE ' .
				$db->quote('datacompliance.notified%'))
			->bind(':userId', $userId, ParameterType::INTEGER);
		$db->setQuery($query)->execute();
	}

	/**
	 * Should I skip creating an audit record?
	 *
	 * @param   bool  $value
	 *
	 * @since        1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function skipAuditRecord(bool $value): void
	{
		$this->skipAuditRecord = $value;
	}

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
	 * @since        1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function wipe(int $userId, string $type = 'user', bool $godMode = false): bool
	{
		if (!$godMode && !$this->checkWipeAbility($userId, $type))
		{
			return false;
		}

		$this->createAuditRecord($userId, $type);

		// Actually delete the records
		PluginHelper::importPlugin('datacompliance');
		PluginHelper::importPlugin('privacy');

		// Set a session variable indicating we are wiping a profile, therefore no change audit trail should be preserved
		Factory::getApplication()->getSession()->set('com_datacompliance.wiping', true);

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

		// Also run Joomla Privacy plugins
		try
		{
			$this->deleteUserWithJoomla($userId);
		}
		catch (Exception $e)
		{
			// Don't care if it fails
		}

		$this->saveAuditRecord($auditItems);

		// Unset the session variable indicating we are wiping a profile
		Factory::getApplication()->getSession()->set('com_datacompliance.wiping', false);

		return true;
	}

	/**
	 * Checks whether Joomla reports a user account as ready for being wiped.
	 *
	 * @param   int   $userId  The user ID which will be deleted
	 * @param   Date  $when    When the deletion will be taking place.
	 *
	 * @return  void
	 * @throws  RuntimeException|Exception  If the user can't be wiped. The message says why.
	 * @since   2.0.4
	 */
	private function checkJoomlaPrivacyWipeAbility(int $userId, Date $when): void
	{
		// This feature is available since Joomla! 3.9.0
		if (version_compare(JVERSION, '3.9.0', 'lt'))
		{
			return;
		}

		// Get a user record
		/** @var User $user */
		$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);

		// If the user does not exist fail early
		if ($user->id != $userId)
		{
			return;
		}

		// Create a (fake) request table object for Joomla's privacy plugins
		/** @var DatabaseDriver $db */
		$db      = $this->getDatabase();
		$request = new RequestTable($db);

		$request->email                    = $user->email;
		$request->requested_at             = $when->toSql();
		$request->status                   = 1;
		$request->request_type             = 'remove';
		$request->confirm_token            = UserHelper::genRandomPassword(32);
		$request->confirm_token_created_at = $when->toSql();

		PluginHelper::importPlugin('privacy');
		$results = $this->runPlugins('onPrivacyCanRemoveData', [$request, $user]);

		/** @var Status $result */
		foreach ($results as $result)
		{
			if (!($result instanceof Status))
			{
				continue;
			}

			if (!$result->canRemove)
			{
				throw new RuntimeException($result->reason);
			}
		}

	}

	/**
	 * Creates (if requested) an audit record for current operation
	 *
	 * @param   int     $userId  The user ID to export
	 * @param   string  $type    user, admin or lifecycle
	 *
	 * @throws  Exception
	 * @since   1.0.0
	 */
	private function createAuditRecord(int $userId, string $type): void
	{
		// Always nuke current audit record instance
		$this->auditRecord = null;

		// Am I asked to ignore creating an audit record? Let's stop here
		if ($this->skipAuditRecord)
		{
			return;
		}

		$this->auditRecord = $this->getMVCFactory()->createTable('Wipetrails', 'Administrator');

		// Do I have an existing data wipe record?
		if (!$this->auditRecord->load(['user_id' => $userId]))
		{
			$this->auditRecord->reset();
			$this->auditRecord->save([
				'user_id' => $userId,
				'type'    => $type,
			]);

			return;
		}

		$isDebug     = defined('JDEBUG') && JDEBUG;
		$isSuperUser = Factory::getApplication()->getIdentity()->authorise('core.admin');
		$isCli       = Factory::getApplication()->isClient('cli');

		if (!($isDebug && ($isCli || $isSuperUser)))
		{
			throw new RuntimeException(Text::_('COM_DATACOMPLIANCE_WIPE_ERR_TRAILEXISTS'));
		}

		$this->auditRecord->type = $type;
	}

	/**
	 * Delete a user record using Joomla's privacy plugins
	 *
	 * @param   int  $userId  The user ID to delete
	 *
	 * @throws  Exception
	 * @since   2.0.4
	 */
	private function deleteUserWithJoomla(int $userId): void
	{
		// This feature is available since Joomla! 3.9.0
		if (version_compare(JVERSION, '3.9.0', 'lt'))
		{
			return;
		}

		// Get a user record
		$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);

		// If the user does not exist fail early
		if ($user->id != $userId)
		{
			return;
		}

		// Create a (fake) request table object for Joomla's privacy plugins
		/** @var DatabaseDriver $db */
		$db      = $this->getDatabase();
		$request = new RequestTable($db);

		$when                              = clone Factory::getDate();
		$request->email                    = $user->email;
		$request->requested_at             = $when->toSql();
		$request->status                   = 1;
		$request->request_type             = 'remove';
		$request->confirm_token            = UserHelper::genRandomPassword(32);
		$request->confirm_token_created_at = $when->toSql();

		PluginHelper::importPlugin('privacy');
		$results = $this->runPlugins('onPrivacyRemoveData', [$request, $user]);
		/** @var Status $result */
		foreach ($results as $result)
		{
			if (!($result instanceof Status))
			{
				continue;
			}

			if (!$result->canRemove)
			{
				throw new RuntimeException($result->reason);
			}
		}
	}

	/**
	 * Execute plugins (system-level triggers) and fetch back an array with their return values.
	 *
	 * @param   string  $event  The event (trigger) name, e.g. onBeforeScratchMyEar
	 * @param   array   $data   A hash array of data sent to the plugins as part of the trigger
	 *
	 * @return  array  A simple array containing the results of the plugins triggered
	 *
	 * @throws  Exception
	 * @since   1.0.0
	 */
	private function runPlugins(string $event, array $data = []): array
	{
		/** @noinspection PhpDeprecationInspection */
		return Factory::getApplication()->triggerEvent($event, $data);
	}

	/**
	 * Saves current audit record with passes audit items. Nothing is performed if we're asked to skip the creation of
	 * an audit record
	 *
	 * @param   array  $auditItems
	 *
	 * @since   1.0.0
	 */
	private function saveAuditRecord(array $auditItems): void
	{
		if ($this->skipAuditRecord)
		{
			return;
		}

		// Update audit record with $auditItems
		$this->auditRecord->items = $auditItems;
		$this->auditRecord->store();

		// Notify plugins
		$eventName  = 'onDataComplianceSaveWipeAuditRecord';
		$event      = new Event($eventName, [$this->auditRecord]);

		Factory::getApplication()->getDispatcher()->dispatch($eventName, $event);
	}
}