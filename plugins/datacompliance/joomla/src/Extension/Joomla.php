<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\DataCompliance\Joomla\Extension;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Helper\Export;
use DateTime;
use Exception;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use RuntimeException;
use SimpleXMLElement;

/**
 * Data Compliance plugin for Core Joomla! User Data
 *
 * @since  1.0.0
 */
class Joomla extends CMSPlugin implements SubscriberInterface
{
	use MVCFactoryAwareTrait;
	use DatabaseAwareTrait;

	/**
	 * Constructor
	 *
	 * @param   DispatcherInterface  &    $subject     The object to observe
	 * @param   array                     $config      An optional associative array of configuration settings.
	 *                                                 Recognized key values include 'name', 'group', 'params',
	 *                                                 'language' (this list is not meant to be comprehensive).
	 * @param   MVCFactoryInterface|null  $mvcFactory  The MVC factory for the Data Compliance component.
	 *
	 * @since   3.0.0
	 */
	public function __construct(&$subject, $config = [], MVCFactoryInterface $mvcFactory = null)
	{
		if (!empty($mvcFactory))
		{
			$this->setMVCFactory($mvcFactory);
		}

		$this->autoloadLanguage = true;

		parent::__construct($subject, $config);
	}

	/**
	 * Return the mapping of event names and public methods in this object which handle them
	 *
	 * @return string[]
	 * @since  3.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		if (!ComponentHelper::isEnabled('com_datacompliance'))
		{
			return [];
		}

		return [
			'onDataComplianceCanDelete'           => 'onDataComplianceCanDelete',
			'onDataComplianceDeleteUser'          => 'onDataComplianceDeleteUser',
			'onDataComplianceExportUser'          => 'onDataComplianceExportUser',
			'onDataComplianceGetWipeBulletpoints' => 'onDataComplianceGetWipeBulletpoints',
			'onDataComplianceGetEOLRecords'       => 'onDataComplianceGetEOLRecords',
		];
	}

	/**
	 * Checks whether a user is safe to be deleted. This plugin prevents deletion on the following conditions:
	 * - The user is a Super User
	 * - The user has backend access
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void  No return value is expected. Throw exceptions when there is a problem.
	 *
	 * @throws  RuntimeException  The error which prevents us from deleting a user
	 */
	public function onDataComplianceCanDelete(Event $event)
	{
		/**
		 * @var   int      $userId The user ID we are asked for permission to delete
		 * @var   string   $type   user, admin or lifecycle
		 * @var   DateTime $when   When is the deletion going to take place? Leaving null means "right now"
		 */
		[$userId, $type, $when] = $event->getArguments();

		$exemptGroups = $this->params->get('exemptgroups', []);
		$jUser        = $this->getJoomlaUserObject($userId);
		$userGroups   = $jUser->getAuthorisedGroups();
		$foundGroups  = array_intersect($userGroups, $exemptGroups);

		if ($foundGroups)
		{
			throw new RuntimeException(Text::_('PLG_DATACOMPLIANCE_JOOMLA_ERR_EXEMPTGROUPS'));
		}

		$user = $this->getJoomlaUserObject($userId);

		if (empty($user))
		{
			throw new RuntimeException(Text::sprintf('PLG_DATACOMPLIANCE_JOOMLA_ERR_UNKNOWNUSER', $userId));
		}

		if ($user->authorise('core.admin'))
		{
			throw new RuntimeException(Text::_('PLG_DATACOMPLIANCE_JOOMLA_ERR_SUPERUSER'));
		}

		if ($user->authorise('core.login.admin'))
		{
			throw new RuntimeException(Text::_('PLG_DATACOMPLIANCE_JOOMLA_ERR_BACKENDUSER'));
		}
	}

	/**
	 * Performs the necessary actions for deleting a user. Returns an array of the infomration categories and any
	 * applicable IDs which were deleted in the process. This information is stored in the audit log. DO NOT include
	 * any personally identifiable information.
	 *
	 * This plugin takes the following actions:
	 * - The user name is pseudonymized to "user1234" where 1234 is the user ID
	 * - The email is pseudonymized to "user1234@example.com" where 1234 is the user ID
	 * - The password is changed to a long, random string\
	 * - Account creation and last access time are set to dummy values 1/1/1999 and 31/12/1999 GMT.
	 * - User notes are deleted
	 * - User fields are deleted
	 * - User keys (#__user_keys) are deleted
	 * - All user groups are removed from #__user_usergroup_map for this user, making it impossible to login
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onDataComplianceDeleteUser(Event $event)
	{
		/**
		 * @var int    $userId The user ID we are asked to delete
		 * @var string $type   The export type (user, admin, lifecycle)
		 */
		[$userId, $type] = $event->getArguments();

		if (empty($userId))
		{
			return;
		}

		$ret = [
			'joomla' => [
				'user'   => $userId,
				'notes'  => [],
				'fields' => [],
				'keys'   => [],
			],
		];

		Log::add("Deleting user #$userId, type ‘{$type}’, Joomla! Core Data", Log::INFO, 'com_datacompliance');

		$user = $this->getJoomlaUserObject($userId);

		$ret['joomla']['notes']  = $this->deleteNotes($user);
		$ret['joomla']['fields'] = $this->deleteFields($user);
		$ret['joomla']['keys']   = $this->deleteKeys($user);

		$this->deleteUserGroups($user);
		$this->pseudonymizeUser($user);

		$this->setEventResult($event, $ret);
	}

	/**
	 * Used for exporting the user information in XML format. The returned data is a SimpleXMLElement document with a
	 * data dump following the structure root > domain > item[...] > column[...].
	 *
	 * This plugin exports the following tables / models:
	 * - #__users
	 * - #__user_notes
	 * - #__user_profiles
	 * - #__user_usergroup_map
	 * - #__user_keys
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onDataComplianceExportUser(Event $event): void
	{
		/** @var int $userId */
		[$userId] = $event->getArguments();

		$db   = $this->getDatabase();
		$user = self::getJoomlaUserObject($userId);

		if (empty($user))
		{
			throw new RuntimeException(Text::sprintf('PLG_DATACOMPLIANCE_JOOMLA_ERR_UNKNOWNUSER', $userId));
		}

		$export = new SimpleXMLElement("<root></root>");

		// #__users
		$domainUser = $export->addChild('domain');
		$domainUser->addAttribute('name', 'users');
		$domainUser->addAttribute('description', 'Joomla! #__users records');

		/** @var \Joomla\CMS\Table\User $userTable */
		$userTable = User::getTable();
		$userTable->load($userId);
		Export::adoptChild($domainUser, Export::exportItemFromJTable($userTable));

		// #__user_notes
		$domainNotes = $export->addChild('domain');
		$domainNotes->addAttribute('name', 'user_notes');
		$domainNotes->addAttribute('description', 'Joomla! #__user_notes records');

		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__user_notes'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($user->id));

		$items = $db->setQuery($query)->loadObjectList();

		foreach ($items as $item)
		{
			Export::adoptChild($domainNotes, Export::exportItemFromObject($item));
		}

		// #__user_profiles
		$domainProfiles = $export->addChild('domain');
		$domainProfiles->addAttribute('name', 'user_profiles');
		$domainProfiles->addAttribute('description', 'Joomla! #__user_profiles records');

		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__user_profiles'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($user->id));

		$items = $db->setQuery($query)->loadObjectList();

		foreach ($items as $item)
		{
			Export::adoptChild($domainNotes, Export::exportItemFromObject($item));
		}

		// #__user_usergroup_map
		$domainGroups = $export->addChild('domain');
		$domainGroups->addAttribute('name', 'user_usergroup_map');
		$domainGroups->addAttribute('description', 'Joomla! #__user_usergroup_map records');

		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__user_usergroup_map'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($user->id));

		$items = $db->setQuery($query)->loadObjectList();

		foreach ($items as $item)
		{
			Export::adoptChild($domainGroups, Export::exportItemFromObject($item));
		}

		// #__user_keys
		$domainKeys = $export->addChild('domain');
		$domainKeys->addAttribute('name', 'user_keys');
		$domainKeys->addAttribute('description', 'Joomla! #__user_keys records');

		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__user_keys'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($user->id));

		$items = $db->setQuery($query)->loadObjectList();

		foreach ($items as $item)
		{
			Export::adoptChild($domainGroups, Export::exportItemFromObject($item));
		}

		$this->setEventResult($event, $export);
	}

	/**
	 * Returns a list of user IDs which are to be removed on $date due to the lifecycle policy. In other words, which
	 * user IDs this plugin considers to be "expired" on $date.
	 *
	 * Not all plugins need to implement this method. Some plugins may implement _only_ this method, e.g. if your
	 * lifecycle policy depends on an external service's results (you could have, for example, LDAP fields to mark
	 * ex-employee records as ripe for garbage collection).
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public function onDataComplianceGetEOLRecords(Event $event): void
	{
		/** @var DateTime $date */
		[$date] = $event->getArguments();

		if (!$this->params->get('lifecycle', 1))
		{
			$this->setEventResult($event, []);
		}

		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('id')
			->from($db->quoteName('#__users'));

		// Users who have not been logged in for at least $threshold months
		$threshold   = (int) $this->params->get('threshold', 18);
		$threshold   = max(1, $threshold);
		$jLastYear   = (clone Factory::getDate())->sub(new \DateInterval("P{$threshold}M"));
		$sqlLastYear = $jLastYear->toSql();
		$query->where($db->quoteName('lastvisitDate') . ' < :lastYear', 'OR')
			->bind(':lastYear', $sqlLastYear, ParameterType::STRING);

		// Users who have never visited the site
		if ($this->params->get('nevervisited', 1))
		{
			$sqlNullDate = $db->getNullDate();
			$query
				->where($db->qn('lastvisitDate') . ' = :nullDate', 'OR')
				->where($db->qn('lastvisitDate') . ' IS NULL ', 'OR')
				->bind(':nullDate', $sqlNullDate);
		}

		// Blocked users (unless they were created or have visited the site during the threshold period)
		if ($this->params->get('blocked', 1))
		{
			$condition = '(' .
				'(' . $db->qn('block') . ' = 1) AND ' .
				'NOT (' . $db->qn('lastvisitDate') . ' >= :lastYear2) AND ' .
				'NOT (' . $db->qn('registerDate') . ' >= :lastYear3)' .
				')';
			// Blocked
			$query
				->where($condition, 'OR')
				->bind(':lastYear2', $sqlLastYear)
				->bind(':lastYear3', $sqlLastYear);
		}

		$this->setEventResult($event, $db->setQuery($query)->loadColumn(0) ?: []);
	}

	/**
	 * Return a list of human readable actions which will be carried out by this plugin if the user proceeds with wiping
	 * their user account.
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onDataComplianceGetWipeBulletpoints(Event $event)
	{
		/**
		 * @var   int    $userId The user ID we are asked to delete
		 * @var   string $type   The export type (user, admin, lifecycle)
		 */
		[$userId, $type] = $event->getArguments();

		$this->setEventResult($event, [
			Text::_('PLG_DATACOMPLIANCE_JOOMLA_ACTIONS_10'),
			Text::_('PLG_DATACOMPLIANCE_JOOMLA_ACTIONS_1'),
			Text::_('PLG_DATACOMPLIANCE_JOOMLA_ACTIONS_2'),
			Text::_('PLG_DATACOMPLIANCE_JOOMLA_ACTIONS_3'),
			Text::_('PLG_DATACOMPLIANCE_JOOMLA_ACTIONS_4'),
			Text::_('PLG_DATACOMPLIANCE_JOOMLA_ACTIONS_5'),
			Text::_('PLG_DATACOMPLIANCE_JOOMLA_ACTIONS_6'),
			Text::_('PLG_DATACOMPLIANCE_JOOMLA_ACTIONS_7'),
			Text::_('PLG_DATACOMPLIANCE_JOOMLA_ACTIONS_8'),
			Text::_('PLG_DATACOMPLIANCE_JOOMLA_ACTIONS_9'),
		]);
	}

	/**
	 * Get the Joomla! user object for the given user ID
	 *
	 * @param   int  $userId  The user ID to return the user for
	 *
	 * @return  User|null
	 * @since   1.0.0
	 */
	protected function getJoomlaUserObject(int $userId): ?User
	{
		$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);

		if (!is_object($user))
		{
			return null;
		}

		if ($user->id != $userId)
		{
			return null;
		}

		return $user;
	}

	/**
	 * Delete user profile fields
	 *
	 * @param   User  $user  The user object we are deleting
	 *
	 * @return  array  IDs of user profile fields removed
	 *
	 * @since   1.0.0
	 */
	private function deleteFields(User $user): array
	{
		Log::add("Deleting user fields", Log::DEBUG, 'com_datacompliance');

		$db = $this->getDatabase();
		$db->setMonitor(null);
		$ids = [];

		$selectQuery = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__user_profiles'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($user->id));

		$deleteQuery = $db->getQuery(true)
			->delete($db->quoteName('#__user_profiles'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($user->id));

		try
		{
			$ids = $db->setQuery($selectQuery)->loadColumn(0);
			$db->setQuery($deleteQuery)->execute();
		}
		catch (Exception $e)
		{
			Log::add("Could not delete user fields: {$e->getMessage()}", Log::ERROR, 'com_datacompliance');
			Log::add("Stack trace: {$e->getTraceAsString()}", Log::ERROR, 'com_datacompliance');
			// Never mind...
		}

		return $ids;
	}

	/**
	 * Delete user keys
	 *
	 * @param   User  $user  The user object we are deleting
	 *
	 * @return  array  IDs of user keys removed
	 * @since   1.0.0
	 */
	private function deleteKeys(User $user): array
	{
		Log::add("Deleting user keys", Log::DEBUG, 'com_datacompliance');

		$db = $this->getDatabase();
		$db->setMonitor(null);
		$ids    = [];
		$userId = $user->id;

		// WTAF?! Trying to bind the user id with bind() results in a fatal error.
		$selectQuery = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__user_keys'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($user->id));
		$deleteQuery = $db->getQuery(true)
			->delete($db->quoteName('#__user_keys'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($user->id));
		try
		{
			$ids = $db->setQuery($selectQuery)->loadColumn(0);
			$db->setQuery($deleteQuery)->execute();
		}
		catch (Exception $e)
		{
			Log::add("Could not delete user keys: {$e->getMessage()}", Log::ERROR, 'com_datacompliance');
			Log::add("Stack trace: {$e->getTraceAsString()}", Log::ERROR, 'com_datacompliance');
			// Never mind...
		}

		return $ids;
	}

	/**
	 * Delete user notes
	 *
	 * @param   User  $user  The user object we are deleting
	 *
	 * @return  array  IDs of user notes removed
	 *
	 * @since   1.0.0
	 */
	private function deleteNotes(User $user): array
	{
		Log::add("Deleting user notes", Log::DEBUG, 'com_datacompliance');

		$db = $this->getDatabase();
		$db->setMonitor(null);

		$ids         = [];
		$selectQuery = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__user_notes'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($user->id));

		$deleteQuery = $db->getQuery(true)
			->delete($db->quoteName('#__user_notes'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($user->id));

		try
		{
			$ids = $db->setQuery($selectQuery)->loadColumn(0);
			$db->setQuery($deleteQuery)->execute();
		}
		catch (Exception $e)
		{
			Log::add("Could not delete user notes: {$e->getMessage()}", Log::ERROR, 'com_datacompliance');
			Log::add("Stack trace: {$e->getTraceAsString()}", Log::ERROR, 'com_datacompliance');
			// Never mind...
		}

		return $ids;
	}

	/**
	 * Delete user groups.
	 *
	 * User group records are linked to user IDs on an 1:1 basis. Therefore exporting them could potentially divulge
	 * information about the user on the basis that a user whose approximate creation date can be inferred and whose
	 * user groups (therefore: membership on our site) we know can be reverse located through third party extension
	 * metadata, e.g. subscription information.
	 *
	 * @param   User  $user  The user object we are deleting
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function deleteUserGroups(User $user): void
	{
		Log::add("Deleting user groups", Log::DEBUG, 'com_datacompliance');

		$db = $this->getDatabase();
		$db->setMonitor(null);
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__user_usergroup_map'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($user->id));

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			Log::add("Could not delete user groups: {$e->getMessage()}", Log::ERROR, 'com_datacompliance');
			Log::add("Stack trace: {$e->getTraceAsString()}", Log::ERROR, 'com_datacompliance');

			// Never mind...
		}
	}

	/**
	 * Pseudonymize a user:
	 * - The user name is pseudonymized to "user1234" where 1234 is the user ID
	 * - The email is pseudonymized to "user1234@example.com" where 1234 is the user ID
	 * - The password is changed to a long, random string\
	 * - Account creation and last access time are set to dummy values 1/1/1999 GMT.
	 *
	 * @param   User  $user
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function pseudonymizeUser(User $user): void
	{
		Log::add("Pseudonymizing user", Log::DEBUG, 'com_datacompliance');

		$jFake               = clone Factory::getDate('1999-01-01 00:00:00');
		$user->name          = "User {$user->id}";
		$user->username      = "user{$user->id}";
		$user->email         = "user{$user->id}@example.com";
		$user->password      = UserHelper::genRandomPassword(64);
		$user->block         = 0;
		$user->sendEmail     = 0;
		$user->registerDate  = $jFake->toSql();
		$user->lastvisitDate = $jFake->toSql();
		$user->activation    = UserHelper::genRandomPassword(32);
		$user->params        = '{}';
		$user->lastResetTime = $jFake->toSql();
		$user->resetCount    = 0;
		$user->requireReset  = 1;

		try
		{
			$result = $user->save();
		}
		catch (Exception $e)
		{
			$result = false;
		}

		if (!$result)
		{
			Log::add("Could not pseudonymise user: {$e->getMessage()}", Log::ERROR, 'com_datacompliance');
			Log::add("Stack trace: {$e->getTraceAsString()}", Log::ERROR, 'com_datacompliance');
		}
	}

	/**
	 * Sets the 'result' argument of an event, building upon previous results
	 *
	 * @param   Event  $event       The event you are handling
	 * @param   mixed  $yourResult  The result value to add to the 'result' argument.
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	private function setEventResult(Event $event, $yourResult): void
	{
		$result = $event->hasArgument('result') ? $event->getArgument('result') : [];

		if (!is_array($result))
		{
			$result = [$result];
		}

		$result[] = $yourResult;

		$event->setArgument('result', $result);
	}
}