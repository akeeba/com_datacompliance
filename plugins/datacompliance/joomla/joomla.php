<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\DataCompliance\Admin\Helper\Export;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserHelper;

defined('_JEXEC') or die;

/**
 * Data Compliance plugin for Core Joomla! User Data
 */
class plgDatacomplianceJoomla extends Joomla\CMS\Plugin\CMSPlugin
{
	protected $container;

	/**
	 * Constructor. Intializes the object:
	 * - Load the plugin's language strings
	 * - Get the com_datacompliance container
	 *
	 * @param   object  $subject  Passed by Joomla
	 * @param   array   $config   Passed by Joomla
	 */
	public function __construct(&$subject, array $config = array())
	{
		$this->autoloadLanguage = true;
		$this->container = \FOF30\Container\Container::getInstance('com_datacompliance');

		parent::__construct($subject, $config);
	}


	/**
	 * Checks whether a user is safe to be deleted. This plugin prevents deletion on the following conditions:
	 * - The user is a Super User
	 * - The user has backend access
	 *
	 * @param   int  $userID  The user ID we are asked for permission to delete
	 *
	 * @return  void  No return value is expected. Throw exceptions when there is a problem.
	 *
	 * @throws  RuntimeException  The error which prevents us from deleting a user
	 */
	public function onDataComplianceCanDelete($userID)
	{
		// TODO Check a "lockdown" user profile field set by Administrators for user accounts for which an active dispute resolution is in progress.

		$user = $this->getJoomlaUserObject($userID);

		if (empty($user))
		{
			throw new RuntimeException(JText::sprintf('PLG_DATACOMPLIANCE_JOOMLA_ERR_UNKNOWNUSER', $userID));
		}

		if ($user->authorise('core.admin'))
		{
			throw new RuntimeException(JText::_('PLG_DATACOMPLIANCE_JOOMLA_ERR_SUPERUSER'));
		}

		if ($user->authorise('core.login.admin'))
		{
			throw new RuntimeException(JText::_('PLG_DATACOMPLIANCE_JOOMLA_ERR_BACKENDUSER'));
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
	 * @param   int     $userID  The user ID we are asked to delete
	 * @param   string  $type    The export type (user, admin, lifecycle)
	 *
	 * @return  array
	 */
	public function onDataComplianceDeleteUser(int $userID, string $type): array
	{
		$ret = [
			'joomla' => [
				'user'   => $userID,
				'notes'  => [],
				'fields' => [],
				'keys'   => [],
			],
		];

		$user = $this->getJoomlaUserObject($userID);

		$ret['joomla']['notes']  = $this->deleteNotes($user);
		$ret['joomla']['fields'] = $this->deleteFields($user);
		$ret['joomla']['keys']   = $this->deleteKeys($user);

		$this->deleteUserGroups($user);
		$this->pseudonymizeUser($user);

		return $ret;
	}

	/**
	 * Return a list of human readable actions which will be carried out by this plugin if the user proceeds with wiping
	 * their user account.
	 *
	 * @param   int     $userID  The user ID we are asked to delete
	 * @param   string  $type    The export type (user, admin, lifecycle)
	 *
	 * @return  string[]
	 */
	public function onDataComplianceGetWipeBulletpoints(int $userID, string $type)
	{
		return [
			JText::_('PLG_DATACOMPLIANCE_JOOMLA_ACTIONS_10'),
			JText::_('PLG_DATACOMPLIANCE_JOOMLA_ACTIONS_1'),
			JText::_('PLG_DATACOMPLIANCE_JOOMLA_ACTIONS_2'),
			JText::_('PLG_DATACOMPLIANCE_JOOMLA_ACTIONS_3'),
			JText::_('PLG_DATACOMPLIANCE_JOOMLA_ACTIONS_4'),
			JText::_('PLG_DATACOMPLIANCE_JOOMLA_ACTIONS_5'),
			JText::_('PLG_DATACOMPLIANCE_JOOMLA_ACTIONS_6'),
			JText::_('PLG_DATACOMPLIANCE_JOOMLA_ACTIONS_7'),
			JText::_('PLG_DATACOMPLIANCE_JOOMLA_ACTIONS_8'),
			JText::_('PLG_DATACOMPLIANCE_JOOMLA_ACTIONS_9'),
		];
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
	 * @param $userID
	 *
	 * @return SimpleXMLElement
	 */
	public function onDataComplianceExportUser($userID): SimpleXMLElement
	{
		$db   = $this->container->db;
		$user = self::getJoomlaUserObject($userID);

		if (empty($user))
		{
			throw new RuntimeException(JText::sprintf('PLG_DATACOMPLIANCE_JOOMLA_ERR_UNKNOWNUSER', $userID));
		}

		$export = new SimpleXMLElement("<root></root>");

		// #__users
		$domainUser = $export->addChild('domain');
		$domainUser->addAttribute('name', 'users');
		$domainUser->addAttribute('description', 'Joomla! #__users records');

		/** @var JTableUser $userTable */
		$userTable = User::getTable();
		$userTable->load($userID);
		Export::adoptChild($domainUser, Export::exportItemFromJTable($userTable));

		// #__user_notes
		$domainNotes = $export->addChild('domain');
		$domainNotes->addAttribute('name', 'user_notes');
		$domainNotes->addAttribute('description', 'Joomla! #__user_notes records');

		$query = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__user_notes'))
			->where($db->qn('user_id') . ' = ' . $db->q($user->id));
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
			->from($db->qn('#__user_profiles'))
			->where($db->qn('user_id') . ' = ' . $db->q($user->id));
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
			->from($db->qn('#__user_usergroup_map'))
			->where($db->qn('user_id') . ' = ' . $db->q($user->id));
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
			->from($db->qn('#__user_keys'))
			->where($db->qn('user_id') . ' = ' . $db->q($user->username));
		$items = $db->setQuery($query)->loadObjectList();

		foreach ($items as $item)
		{
			Export::adoptChild($domainGroups, Export::exportItemFromObject($item));
		}

		return $export;
	}

	/**
	 * Returns a list of user IDs which are to be removed on $date due to the lifecycle policy. In other words, which
	 * user IDs this plugin considers to be "expired" on $date.
	 *
	 * Not all plugins need to implement this method. Some plugins may implement _only_ this method, e.g. if your
	 * lifecycle policy depends on an external service's results (you could have, for example, LDAP fields to mark
	 * ex-employee records as ripe for garbage collection).
	 *
	 * @param   DateTime $date
	 *
	 * @return  int[]
	 *
	 * @throws Exception
	 */
	public function onDataComplianceGetEOLRecords(DateTime $date): array
	{
		if (!$this->params->get('lifecycle', 1))
		{
			return [];
		}

		$db    = $this->container->db;
		$query = $db->getQuery(true)
		            ->select('id')
		            ->from($db->qn('#__users'))
		;

		// Users who have not been logged in for at least $threshold months
		$threshold = (int) $this->params->get('threshold', 18);
		$threshold = min(1, $threshold);
		$jLastYear = $this->container->platform->getDate()->sub(new DateInterval("P{$threshold}M"));
		$query->where($db->qn('block') . ' < ' . $db->qn($jLastYear->toSql()), 'OR');

		// Users who have never visited the site
		if ($this->params->get('nevervisted', 1))
		{
			$query->where($db->qn('lastvisitDate') . ' = ' . $db->qn($db->getNullDate()), 'OR');
		}

		// Blocked users
		if ($this->params->get('blocked', 1))
		{
			$query
				->where($db->qn('block') . ' = ' . $db->qn(1), 'OR');
		}

		// Users with pre-Joomla! 3.2 password format
		if ($this->params->get('obsoletepassword', 1))
		{
			$query
				->where('(' .
					$db->qn('password') . ' LIKE ' . $db->qn('%:%') .
					' AND NOT ' . $db->qn('password') . ' LIKE ' . $db->qn('$%')
					. ')', 'OR');
		}

		return $db->setQuery($query)->loadColumn(0);
	}

	/**
	 * Get the Joomla! user object for the given user ID
	 *
	 * @param   int  $userID  The user ID to return the user for
	 *
	 * @return  User|JUser
	 */
	protected function getJoomlaUserObject($userID)
	{
		$user = $this->container->platform->getUser($userID);

		if (!is_object($user))
		{
			return null;
		}

		if ($user->id != $userID)
		{
			return null;
		}

		return $user;
	}

	/**
	 * Pseudonymize a user:
	 * - The user name is pseudonymized to "user1234" where 1234 is the user ID
	 * - The email is pseudonymized to "user1234@example.com" where 1234 is the user ID
	 * - The password is changed to a long, random string\
	 * - Account creation and last access time are set to dummy values 1/1/1999 GMT.
	 *
	 * @param   User   $user
	 *
	 * @return  void
	 */
	private function pseudonymizeUser(User $user)
	{
		$jFake               = $this->container->platform->getDate('1999-01-01 00:00:00');
		$user->name          = "User {$user->id}";
		$user->username      = "user{$user->id}";
		$user->email         = "user{$user->id}@example.com";
		$user->password      = UserHelper::genRandomPassword(64);
		$user->block         = 0;
		$user->sendEmail     = 0;
		$user->registerDate  = $jFake->toSql();
		$user->lastvisitDate = $jFake->toSql();
		$user->activation    = UserHelper::genRandomPassword(32);
		if (!is_object($user->params) || !($user->params instanceof \Joomla\Registry\Registry))
		{
			$user->params = '{}';
		}
		else
		{
			$user->params->loadString('{}');
		}
		$user->lastResetTime = $jFake->toSql();
		$user->resetCount    = 0;
		$user->requireReset  = 1;
		$user->save(false);
	}

	/**
	 * Delete user notes
	 *
	 * @param   User  $user  The user object we are deleting
	 *
	 * @return  array  IDs of user notes removed
	 */
	private function deleteNotes(User $user): array
	{
		$db          = $this->container->db;
		$ids         = [];
		$selectQuery = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__user_notes'))
			->where($db->qn('user_id') . ' = ' . $db->q($user->id));

		$deleteQuery = $db->getQuery(true)
			->delete($db->qn('#__user_notes'))
			->where($db->qn('user_id') . ' = ' . $db->q($user->id));

		try
		{
			$ids = $db->setQuery($selectQuery)->loadColumn(0);
			$db->setQuery($deleteQuery)->execute();
		}
		catch (Exception $e)
		{
			// Never mind...
		}

		return $ids;
	}

	/**
	 * Delete user profile fields
	 *
	 * @param   User  $user  The user object we are deleting
	 *
	 * @return  array  IDs of user profile fields removed
	 */
	private function deleteFields(User $user): array
	{
		$db = $this->container->db;
		$ids         = [];

		$selectQuery = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__user_profiles'))
			->where($db->qn('user_id') . ' = ' . $db->q($user->id));

		$deleteQuery = $db->getQuery(true)
			->delete($db->qn('#__user_profiles'))
			->where($db->qn('user_id') . ' = ' . $db->q($user->id));

		try
		{
			$ids = $db->setQuery($selectQuery)->loadColumn(0);
			$db->setQuery($deleteQuery)->execute();
		}
		catch (Exception $e)
		{
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
	 */
	private function deleteKeys(User $user): array
	{
		$db = $this->container->db;
		$ids         = [];

		$selectQuery = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__user_keys'))
			->where($db->qn('user_id') . ' = ' . $db->q($user->username));

		$deleteQuery = $db->getQuery(true)
			->delete($db->qn('#__user_keys'))
			->where($db->qn('user_id') . ' = ' . $db->q($user->username));

		try
		{
			$ids = $db->setQuery($selectQuery)->loadColumn(0);
			$db->setQuery($deleteQuery)->execute();
		}
		catch (Exception $e)
		{
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
	 */
	private function deleteUserGroups(User $user)
	{
		$db = $this->container->db;
		$query = $db->getQuery(true)
			->delete($db->qn('#__user_usergroup_map'))
			->where($db->qn('user_id') . ' = ' . $db->q($user->id));

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			// Never mind...
		}
	}
}