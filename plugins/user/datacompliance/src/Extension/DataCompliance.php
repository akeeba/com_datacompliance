<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\User\DataCompliance\Extension;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Table\ConsenttrailsTable;
use Akeeba\Component\DataCompliance\Administrator\Table\UsertrailsTable;
use Exception;
use InvalidArgumentException;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Table\Menu;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

class DataCompliance extends CMSPlugin implements SubscriberInterface
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
			'onContentPrepareForm' => 'onContentPrepareForm',
			'onUserBeforeSave'     => 'onUserBeforeSave',
			'onUserLogin'          => 'onUserLogin',
		];
	}

	/**
	 * Adds additional fields to the user editing form
	 *
	 * @param   Event  $event  The event to process
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function onContentPrepareForm(Event $event): void
	{
		/**
		 * @var Form  $form The form to be altered.
		 * @var mixed $data The associated data for the form.
		 */
		[$form, $data] = $event->getArguments();

		if (!($form instanceof Form))
		{
			throw new InvalidArgumentException('JERROR_NOT_A_FORM');
		}

		// Check we are manipulating a valid form.
		$name = $form->getName();

		if (!in_array($name, ['com_admin.profile', 'com_users.user', 'com_users.profile', 'com_users.registration']))
		{
			$this->setEventResult($event, true);

			return;
		}

		$layout = $this->getApplication()->input->getCmd('layout', 'default');

		/**
		 * Joomla is kinda brain-dead. When we have a menu item to the Edit Profile page it does not push the layout
		 * into the Input (as opposed with option and view) so I have to go in and dig it out myself. Yikes!
		 */
		$itemId = $this->getApplication()->input->getInt('Itemid');

		if ($itemId)
		{
			try
			{
				/** @var Menu $menuItem */
				$menuItem = new Menu($this->getDatabase());

				if (!$menuItem->load($itemId))
				{
					$this->setEventResult($event, true);

					return;
				}

				$uri    = new Uri($menuItem->link);
				$layout = $uri->getVar('layout', $layout);
			}
			catch (Exception $e)
			{
			}
		}

		if ($this->getApplication()->isClient('site') && !in_array($layout, ['edit', 'default']))
		{
			$this->setEventResult($event, true);

			return;
		}

		// Get the user ID
		$id = null;

		if (is_array($data))
		{
			$id = isset($data['id']) ? $data['id'] : null;
		}
		elseif (is_object($data) && is_null($data) && ($data instanceof Registry))
		{
			$id = $data->get('id');
		}
		elseif (is_object($data) && !is_null($data))
		{
			$id = isset($data->id) ? $data->id : null;
		}

		$user = empty($id)
			? $this->getApplication()->getIdentity()
			: Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($id);

		// Make sure the loaded user is the correct one
		if ($user->id != $id)
		{
			$currentUser = $this->getApplication()->getIdentity();
		}

		// Make sure I am either editing myself (you can NOT make choices on behalf of another user).
		if (!$this->canEditUser($user))
		{
			$this->setEventResult($event, true);

			return;
		}

		// Add the fields to the form.
		Form::addFormPath(__DIR__ . '/../../datacompliance');

		// At this point we should load our language files.
		$this->loadLanguage();

		// Special handling for profile overview page
		if ($layout == 'default')
		{
			/** @var ConsenttrailsTable $consentTable */
			$consentTable = new ConsenttrailsTable($this->getDatabase());
			$hasConsent   = $consentTable->load($id) && ($consentTable->enabled == 1);

			/**
			 * We cannot pass a boolean or integer; if it's false/0 Joomla! will display "No information entered". We
			 * cannot use a list field to display it in a human readable format, Joomla! just dumps the raw value if you
			 * use such a field. So all I can do is pass raw text. Um, whatever.
			 */
			if (is_object($data))
			{
				$data->datacompliance = [
					'hasconsent' => $hasConsent ? Text::_('JYES') : Text::_('JNO'),
				];
			}
			elseif (is_array($data))
			{
				$data['datacompliance'] = [
					'hasconsent' => $hasConsent ? Text::_('JYES') : Text::_('JNO'),
				];
			}

			$form->loadFile('list', false);
			$this->setEventResult($event, true);

			return;
		}

		// Profile edit page
		$form->loadFile('datacompliance', false);
		$this->setEventResult($event, true);
	}

	/**
	 * Logs any changes to the user information
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function onUserBeforeSave(Event $event)
	{
		/**
		 * @var   array   $oldUser Holds the old user data.
		 * @var   boolean $isNew   True if a new user is stored.
		 * @var   array   $newUser Holds the new user data.
		 */
		[$oldUser, $isNew, $newUser] = $event->getArguments();
		$session = $this->getApplication()->getSession();

		/**
		 * Are we wiping a user data profile? If so, we have to NOT log anything.
		 *
		 * If we were not to do that, the use profile wipe would result in all of the personal information being logged
		 * in the user changes audit trail, beating the purpose of the data wipe and possibly being a reason for GDRP
		 * fines if a data breach occurs.
		 */
		if ($session->get('com_datacompliance.wiping', false))
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
		/** @var DatabaseDriver $db */
		$db      = $this->getDatabase();

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
			$changedOldParams = [];
			$changedNewParams = [];

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
		/** @var UsertrailsTable $trail */
		$trail = new UsertrailsTable($db);
		$trail->save([
			'user_id'    => $newUser['id'],
			'items'      => $changes,
			'created_on' => (clone Factory::getDate())->toSql(),
			'created_by' => $this->getApplication()->getIdentity()->id,
		]);
	}

	/**
	 * Resets the user's notification for lifecycle removal flag from their account.
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function onUserLogin(Event $event): void
	{
		/**
		 * @var   array $response User login response
		 * @var   array $options  Options to the user login
		 */
		[$response, $options] = $event->getArguments();

		/**
		 * Do not go through the model as it ends up destroying the session when the Remember Me plugin tries to log you
		 * back in.
		 */
		$userid = UserHelper::getUserId($response['username']);
		$db     = $this->getDatabase();
		$query  = $db->getQuery(true)
			->delete($db->qn('#__user_profiles'))
			->where($db->qn('user_id') . ' = ' . $userid)
			->where($db->qn('profile_key') . ' LIKE ' . $db->q('datacompliance.notified%'));

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			// Ignore it
		}

		$this->setEventResult($event, true);
	}

	/**
	 * Is the current user allowed to edit the TFA configuration of $user? To do so I must be editing my own account.
	 * Since the content must be explicit under GDPR nobody can do it on your behalf.
	 *
	 * @param   User|null  $user  The user you want to know if we're allowed to edit
	 *
	 * @return  bool
	 *
	 * @since   1.0.0
	 */
	private function canEditUser(?User $user = null): bool
	{
		// I can edit myself
		if (empty($user))
		{
			return true;
		}

		// Guests can't have data compliance preferences
		if ($user->guest)
		{
			return false;
		}

		// Get the currently logged in used
		$myUser = $this->getApplication()->getIdentity();

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
	 * Get the custom field (com_fields) changes for logging purposes
	 *
	 * @param   array           $newUser  The new user record information [IN]
	 * @param   DatabaseDriver  $db       Database driver object
	 * @param   array           $changes  The changes to record [OUT]
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function getCustomFieldsChanges(array $newUser, DatabaseDriver $db, array &$changes): void
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
	 * Get the user group changes for logging purposes
	 *
	 * @param   array           $oldUser  The old user record [IN]
	 * @param   array           $newUser  The new user record [IN]
	 * @param   DatabaseDriver  $db       The database driver object
	 * @param   array           $changes  The changes to record [OUT]
	 *
	 * @return  void
	 *
	 * @since  1.0.0
	 */
	private function getUserGroupChanges(array $oldUser, array $newUser, DatabaseDriver $db, array &$changes): void
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
	 * @param   array           $newUser  The new user record [IN]
	 * @param   DatabaseDriver  $db       The database driver object
	 * @param   array           $changes  The changes to record [OUT]
	 *
	 * @return  void
	 *
	 * @since  1.0.0
	 */
	private function getUserProfileChanges(array &$newUser, DatabaseDriver $db, array &$changes): void
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
		$changedOldProfile    = [];
		$changedNewProfile    = [];

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