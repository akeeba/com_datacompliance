<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use FOF30\Container\Container;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Menu;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;

// Prevent direct access
defined('_JEXEC') or die;

// Minimum PHP version check
if (!version_compare(PHP_VERSION, '7.1.0', '>='))
{
	return;
}

// Make sure Akeeba DataCompliance is installed
if (!file_exists(JPATH_ADMINISTRATOR . '/components/com_datacompliance'))
{
	return;
}

// Load FOF
if (!defined('FOF30_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof30/include.php'))
{
	return;
}

/**
 * Akeeba DataCompliance User Plugin
 *
 * Allows the user to access the Data Options page from within the User Profile page
 */
class PlgUserDatacompliance extends JPlugin
{
	/**
	 * Are we enabled, all requirements met etc?
	 *
	 * @var   bool
	 *
	 * @since   1.0.0
	 */
	public $enabled = true;

	/**
	 * The component's container
	 *
	 * @var   Container
	 *
	 * @since   1.0.0
	 */
	private $container = null;

	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An optional associative array of configuration settings.
	 *                             Recognized key values include 'name', 'group', 'params', 'language'
	 *                             (this list is not meant to be comprehensive).
	 *
	 * @since   1.0.0
	 */
	public function __construct($subject, array $config = array())
	{
		parent::__construct($subject, $config);

		try
		{
			if (!JComponentHelper::isInstalled('com_datacompliance') || !JComponentHelper::isEnabled('com_datacompliance'))
			{
				$this->enabled = false;
			}
			else
			{
				$this->container = Container::getInstance('com_datacompliance');
			}
		}
		catch (Exception $e)
		{
			$this->enabled = false;
		}
	}

	/**
	 * Adds additional fields to the user editing form
	 *
	 * @param   JForm $form The form to be altered.
	 * @param   mixed $data The associated data for the form.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 *
	 * @throws Exception
	 */
	public function onContentPrepareForm($form, $data)
	{
		if (!($form instanceof JForm))
		{
			throw new InvalidArgumentException('JERROR_NOT_A_FORM');
		}

		// Check we are manipulating a valid form.
		$name = $form->getName();

		if (!in_array($name, array('com_admin.profile', 'com_users.user', 'com_users.profile', 'com_users.registration')))
		{
			return true;
		}

		$layout = JFactory::getApplication()->input->getCmd('layout', 'default');

		/**
		 * Joomla is kinda brain-dead. When we have a menu item to the Edit Profile page it does not push the layout
		 * into the Input (as opposed with option and view) so I have to go in and dig it out myself. Yikes!
		 */
		$itemId = Factory::getApplication()->input->getInt('Itemid');

		if ($itemId)
		{
			try
			{
				/** @var Menu $menuItem */
				$menuItem = Table::getInstance('Menu');
				$menuItem->load($itemId);
				$uri    = new Uri($menuItem->link);
				$layout = $uri->getVar('layout', $layout);
			}
			catch (Exception $e)
			{
			}
		}

		if (!$this->container->platform->isBackend() && !in_array($layout, array('edit', 'default')))
		{
			return true;
		}

		// Get the user ID
		$id = null;

		if (is_array($data))
		{
			$id = isset($data['id']) ? $data['id'] : null;
		}
		elseif (is_object($data) && is_null($data) && ($data instanceof JRegistry))
		{
			$id = $data->get('id');
		}
		elseif (is_object($data) && !is_null($data))
		{
			$id = isset($data->id) ? $data->id : null;
		}

		$user = JFactory::getUser($id);

		// Make sure the loaded user is the correct one
		if ($user->id != $id)
		{
			$currentUser = $this->container->platform->getUser();
		}

		// Make sure I am either editing myself (you can NOT make choices on behalf of another user).
		if (!$this->canEditUser($user))
		{
			return true;
		}

		// Add the fields to the form.
		JForm::addFormPath(dirname(__FILE__) . '/datacompliance');

		// At this point we should load our language files.
		$this->loadLanguage();

		// Special handling for profile overview page
		if ($layout == 'default')
		{
			/** @var \Akeeba\DataCompliance\Site\Model\Consenttrails $consentModel */
			$consentModel = $this->container->factory->model('Consenttrails')->tmpInstance();

			try
			{
				$consentModel->findOrFail(['created_by' => $id]);
				$hasConsent = $consentModel->enabled ? 1 : 0;
			}
			catch (\FOF30\Model\DataModel\Exception\RecordNotLoaded $e)
			{
				$hasConsent = 0;
			}

			/**
			 * We cannot pass a boolean or integer; if it's false/0 Joomla! will display "No information entered". We
			 * cannot use a list field to display it in a human readable format, Joomla! just dumps the raw value if you
			 * use such a field. So all I can do is pass raw text. Um, whatever.
			 */
			$data->datacompliance = array(
				'hasconsent' => $hasConsent ? JText::_('JYES') : JText::_('JNO')
			);

			$form->loadFile('list', false);

			return true;
		}

		// Profile edit page
		$form->loadFile('datacompliance', false);

		return true;
	}

	/**
	 * Is the current user allowed to edit the TFA configuration of $user? To do so I must be editing my own account.
	 * Since the content must be explicit under GDPR nobody can do it on your behalf.
	 *
	 * @param   JUser|\Joomla\CMS\User\User  $user  The user you want to know if we're allowed to edit
	 *
	 * @return  bool
	 *
	 * @since   1.0.0
	 */
	private function canEditUser($user = null)
	{
		// I can edit myself
		if (empty($user))
		{
			return true;
		}

		// Guests can't have data complaince preferences
		if ($user->guest)
		{
			return false;
		}

		// Get the currently logged in used
		$myUser = $this->container->platform->getUser();

		// Same user? I can edit myself
		if ($myUser->id == $user->id)
		{
			return true;
		}

		// Can I export users?
		if ($myUser->authorise('export', 'com_datacompliance'))
		{
			return true;
		}

		// Can I delete users?
		if ($myUser->authorise('wipe', 'com_datacompliance'))
		{
			return true;
		}

		// Nope. I am not authorized.
		return false;
	}

	/**
	 * Logs any changes to the user information
	 *
	 * @param   array   $oldUser Holds the old user data.
	 * @param   boolean $isNew   True if a new user is stored.
	 * @param   array   $newUser Holds the new user data.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function onUserBeforeSave($oldUser, $isNew, $newUser)
	{
		/**
		 * Are we wiping a user data profile? If so, we have to NOT log anything.
		 *
		 * If we were not to do that, the use profile wipe would result in all of the personal information being logged
		 * in the user changes audit trail, beating the purpose of the data wipe and possibly being a reason for GDRP
		 * fines if a data breach occurs.
		 */
		if ($this->container->platform->getSessionVar('wiping', false, 'com_datacompliance'))
		{
			return;
		}

		// We do not take any actions for new users
		if ($isNew)
		{
			return;
		}

		// Generate the log of user account changes
		$changes = [];
		$db      = JFactory::getDbo();

		/**
		 * Check all the main user fields for changes. We exclude the following fields:
		 * - id: it's always the same (the ID of the user being edited).
		 * - password: passwords must not be logged. Handled separately.
		 * - lastvisitDate: simply records the last login time. This is not important for logging reasons.
		 * - params: we need deeper change tracking. Handled separately.
		 * - otpKey: Two Factor Authentication setup changes are not logged verbatim. Handled separately.
		 * - otep: Two Factor Authentication setup changes are not logged verbatim. Handled separately.
		 * - groups: Groups need special handling.
		 * - profile: User profile fields require special handling.
		 * - com_fields: Custom fields require special handling.
		 */
		$exemptFields = [
			'isRoot', 'userHelper', 'password1', 'password2', 'email1', 'email2',
			'id', 'password', 'lastvisitDate', 'params', 'otpKey', 'otep', 'groups', 'profile',
			'com_fields',
		];
		$allFields    = array_merge(array_keys($oldUser), array_keys($newUser));
		$allFields    = array_diff($allFields, $exemptFields);

		foreach ($allFields as $k)
		{
			$oldValue = isset($oldUser[$k]) ? $oldUser[$k] : null;
			$newValue = isset($newUser[$k]) ? $newUser[$k] : null;

			if ($oldValue == $newValue)
			{
				continue;
			}

			$changes[$k] = [
				'from' => $oldValue,
				'to'   => $newValue,
			];
		}

		// Check for password change
		if ($newUser['password'] && $oldUser['password'] != $newUser['password'])
		{
			$changes['password'] = [
				'from' => '(plaintext passwords are not logged for security reasons)',
				'to'   => '(plaintext passwords are not logged for security reasons)',
			];
		}

		if (isset($newUser['password_clear']))
		{
			$changes['password_clear'] = [
				'from' => '(plaintext passwords are not logged for security reasons)',
				'to'   => '(plaintext passwords are not logged for security reasons)',
			];
		}

		// Two factor authentication
		if (
			(isset($oldUser['otpKey']) && isset($newUser['otpKey']) && ($oldUser['otpKey'] != $newUser['otpKey'])) ||
			(isset($oldUser['otep']) && isset($newUser['otep']) && ($oldUser['otep'] != $newUser['otep']))
		)
		{
			$changes['two_factor'] = [
				'from' => '(Two Factor Authentication settings are not logged for security reasons)',
				'to'   => '(Two Factor Authentication settings are not logged for security reasons)',
			];
		}

		// User configuration parameters
		if ($oldUser['params'] != $newUser['params'])
		{
			// Decode oldParams
			$oldUserParams    = json_decode($oldUser['params'], true);
			$newUserParams    = json_decode($newUser['params'], true);
			$changedOldParams = array();
			$changedNewParams = array();

			foreach ($newUserParams as $paramName => $newParamValue)
			{
				if (!isset($oldUserParams[$paramName]) && $newParamValue)
				{
					$changedOldParams[$paramName] = null;
					$changedNewParams[$paramName] = $newParamValue;
				}
				else
				{
					if (isset($oldUserParams[$paramName]))
					{
						$oldParamValue = $oldUserParams[$paramName];
						if ($oldParamValue != $newParamValue)
						{
							$changedOldParams[$paramName] = $oldParamValue;
							$changedNewParams[$paramName] = $newParamValue;
						}
					}
				}
			}

			if (count($changedNewParams))
			{
				$changes['change_params'] = [
					'from' => $changedOldParams,
					'to'   => $changedNewParams,
				];
			}
		}

		// User groups
		$this->getUserGroupChanges($oldUser, $newUser, $db, $changes);

		// User profile fields
		$this->getUserProfileChanges($newUser, $db, $changes);

		// Custom fields
		$this->getCustomFieldsChanges($newUser, $db, $changes);

		// No changes? Nothing to record.
		if (empty($changes))
		{
			return;
		}

		// Record the user profile changes log entry
		/** @var \Akeeba\DataCompliance\Admin\Model\Usertrails $trail */
		$trail = $this->container->factory->model('Usertrails')->tmpInstance();
		$trail->create([
			'user_id' => $newUser['id'],
			'items'   => $changes,
		]);

		return;
	}

	/**
	 * Get the user group changes for logging purposes
	 *
	 * @param   array            $oldUser
	 * @param   array            $newUser
	 * @param   JDatabaseDriver  $db
	 * @param   array            $changes
	 *
	 * @return  void
	 *
	 * @since  1.0.0
	 */
	private function getUserGroupChanges($oldUser, $newUser, $db, &$changes)
	{
		$oldGroupIDs   = array_map('intval', $oldUser['groups']);
		$newGroupIDs   = array_map('intval', $newUser['groups']);
		$groupsChanged = !(array_diff($oldGroupIDs, $newGroupIDs) === array_diff($newGroupIDs, $oldGroupIDs));

		if (!$groupsChanged)
		{
			return;
		}

		try
		{
			// Get the names of old groups
			$query     = $db->getQuery(true)
				->select($db->qn('title'))
				->from($db->qn('#__usergroups'))
				->where($db->qn('id') . 'IN (' . implode(',', $oldGroupIDs) . ')');
			$oldGroups = $db->setQuery($query)->loadColumn();

			// Get the names of old groups
			$query     = $db->getQuery(true)
				->select($db->qn('title'))
				->from($db->qn('#__usergroups'))
				->where($db->qn('id') . 'IN (' . implode(',', $newGroupIDs) . ')');
			$newGroups = $db->setQuery($query)->loadColumn();

			$changes['usergroups'] = [
				'from' => $oldGroups,
				'to'   => $newGroups,
			];

		}
		catch (Exception $e)
		{
		}
	}

	/**
	 * Get the user profile changes for logging purposes
	 *
	 * @param   array            $newUser
	 * @param   JDatabaseDriver  $db
	 * @param   array            $changes
	 *
	 * @return  void
	 *
	 * @since  1.0.0
	 */
	private function getUserProfileChanges(&$newUser, $db, &$changes)
	{
		if (!isset($newUser['profile']))
		{
			return;
		}

		if (!count($newUser['profile']))
		{
			return;
		}

		// Found an extended user profile, go on and load old values to compare
		$query = $db->getQuery(true)
			->select([
				$db->qn('profile_key', 'key'),
				$db->qn('profile_value', 'value'),
			])
			->from($db->qn('#__user_profiles'))
			->where($db->qn('user_id') . ' == ' . (int) $newUser['id']);

		try
		{
			$profileValuesInDatabase = $db->setQuery($query)->loadAssocList('key');
		}
		catch (Exception $e)
		{
			return;
		}

		// Decode oldParams
		$oldUserProfileValues = $profileValuesInDatabase;
		$newUserProfileValues = $newUser['profile'];
		$changedOldProfile    = array();
		$changedNewProfile    = array();

		foreach ($newUserProfileValues as $paramName => $newParamValue)
		{
			if (!isset($oldUserProfileValues['profile.' . $paramName]) && $newParamValue)
			{
				$changedOldProfile[$paramName] = null;
				$changedNewProfile[$paramName] = $newParamValue;

				continue;
			}

			if (!isset($oldUserProfileValues['profile.' . $paramName]))
			{
				continue;
			}

			// The value is json_encoded into the database rows by the user profile plugin
			$oldParamValue = json_decode($oldUserProfileValues['profile.' . $paramName]['value']);

			if ($oldParamValue != $newParamValue)
			{
				$changedOldProfile[$paramName] = $oldParamValue;
				$changedNewProfile[$paramName] = $newParamValue;
			}
		}

		if (count($changedNewProfile))
		{
			return;
		}

		$changes['profile'] = [
			'from' => $changedOldProfile,
			'to'   => $changedNewProfile,
		];
	}

	/**
	 * Get the custom field (com_fields) changes for logging purposes
	 *
	 * @param   array            $newUser
	 * @param   JDatabaseDriver  $db
	 * @param   array            $changes
	 */
	private function getCustomFieldsChanges($newUser, $db, &$changes)
	{
		if (!isset($newUser['com_fields']))
		{
			return;
		}

		if (!count($newUser['com_fields']))
		{
			return;
		}

		// Found an extended user profile by custom fields, go on and load old values to compare
		$postedCustomFields = array_keys($newUser['com_fields']);
		$postedCustomFields = array_map([$db, 'quote'], $postedCustomFields);
		$postedCustomFields = implode(',', $postedCustomFields);

		$query = $db->getQuery(true)
			->select([
				$db->qn('name', 'key'),
				$db->qn('value'),
			])
			->from($db->qn('#__fields') . ' AS ' . $db->qn('f'))
			->innerJoin(
				$db->qn('#__fields_values') . ' AS ' . $db->qn('v') .
				'ON ' . $db->qn('f.id') . ' = ' . $db->qn('v.field_id')
			)->where($db->qn('v.item_id') . ' = ' . (int) $newUser['id'])
			->where($db->qn('f.state') . ' = 1')
			->where($db->qn('f.name') . ' IN(' . $postedCustomFields . ')');

		try
		{
			$oldProfileFieldsValues = $db->setQuery($query)->loadAssocList('key');
		}
		catch (Exception $e)
		{
			return;
		}

		// Decode oldParams
		$oldUserProfileFieldsValues = $oldProfileFieldsValues;
		$newUserProfileFieldsValues = $newUser['com_fields'];
		$changedFieldsOldProfile    = [];
		$changedFieldsNewProfile    = [];

		foreach ($newUserProfileFieldsValues as $paramName => $newParamValue)
		{
			if (!isset($oldUserProfileFieldsValues[$paramName]) && $newParamValue)
			{
				$changedFieldsOldProfile[$paramName] = null;
				$changedFieldsNewProfile[$paramName] = $newParamValue;

				continue;
			}

			if (!isset($oldUserProfileFieldsValues[$paramName]))
			{
				continue;
			}

			// The value is json_encoded into the database rows by the user profile plugin
			$oldParamValue = $oldUserProfileFieldsValues[$paramName]['value'];

			if ($oldParamValue != $newParamValue)
			{
				$changedFieldsOldProfile[$paramName] = $oldParamValue;
				$changedFieldsNewProfile[$paramName] = $newParamValue;
			}
		}

		if (!count($changedFieldsNewProfile))
		{
			return;
		}

		$changes['change_params'] = [
			'from' => $changedFieldsOldProfile,
			'to'   => $changedFieldsNewProfile,
		];
	}

	/**
	 * Resets the user's notification for lifecycle removal flag from their account.
	 *
	 * @param   array  $response
	 * @param   array  $options
	 *
	 * @return  bool
	 */
	public function onUserLogin($response, $options)
	{
		if (!$this->enabled)
		{
			return true;
		}

		/**
		 * Do not go through the model as it ends up destroying the session when the Remember Me plugin tries to log you
		 * back in.
		 */
		$userid = JUserHelper::getUserId($response['username']);
		$db     = JFactory::getDbo();
		$query  = $db->getQuery(true)
			->delete($db->qn('#__user_profiles'))
			->where($db->qn('user_id') . ' = ' . $userid)
			->where($db->qn('profile_key') . ' LIKE ' . $db->q('datacompliance.notified%'));

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (\Exception $e)
		{
			// Ignore it
		}

		return true;
	}

}
