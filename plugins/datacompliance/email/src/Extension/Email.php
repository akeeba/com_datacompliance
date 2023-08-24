<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\DataCompliance\Email\Extension;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Helper\TemplateEmails;
use Akeeba\Component\DataCompliance\Site\Model\OptionsModel;
use Exception;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * Data Compliance plugin to send emails on user deletion
 *
 * @since  1.0.0
 */
class Email extends CMSPlugin implements SubscriberInterface
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
			'onDataComplianceDeleteUser' => 'onDataComplianceDeleteUser',
		];
	}

	/**
	 * Performs the necessary actions for deleting a user. Returns an array of the information categories and any
	 * applicable IDs which were deleted in the process. This information is stored in the audit log. DO NOT include
	 * any personally identifiable information.
	 *
	 * Sends emails to the user and the administrator.
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public function onDataComplianceDeleteUser(Event $event)
	{
		/**
		 * @var int    $userId The user ID we are asked to delete
		 * @var string $type   The export type (user, admin, lifecycle)
		 */
		[$userId, $type] = array_values($event->getArguments());

		$this->setEventResult($event, []);

		$session = $this->getApplication()->getSession();

		if ($session->get('com_datacompliance.__audit_replay', 0))
		{
			Log::add("Will NOT send email", Log::DEBUG, 'com_datacompliance');

			return;
		}

		$emailUser  = $this->params->get('users', 1);
		$emailAdmin = $this->params->get('admins', 1);

		// Check if we have to send any emails
		if (!$emailUser && !$emailAdmin)
		{
			return;
		}

		Log::add("Sending email about deleting user #$userId, type ‘{$type}’", Log::INFO, 'com_datacompliance');

		// Get the actions
		$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);

		// Get the actions carried out for the user
		/** @var OptionsModel $optionsModel */
		$optionsModel = $this->mvcFactory->createModel('Options', 'Administrator');
		$actionsList  = $optionsModel->getBulletPoints($user, $type);
		$actionsHtml  = "<ul>\n";

		foreach ($actionsList as $action)
		{
			$actionsHtml .= "<li>$action</li>";
		}

		$actionsHtml .= "</ul>\n";

		$emailVariables =
			[
				'name'          => $user->name,
				'email'         => $user->email,
				'username'      => $user->username,
				'registerdate'  => $user->registerDate,
				'lastvisitdate' => $user->lastvisitDate,
				'requirereset'  => $user->requireReset,
				'resetcount'    => $user->resetCount,
				'lastresettime' => $user->lastResetTime,
				'activation'    => empty($user->activation) ? Text::_('JNO') : $user->activation,
				'block'         => $user->block ? Text::_('JYES') : Text::_('JNO'),
				'id'            => $user->id,
				'actions'       => $actionsHtml,
				'actions_text'  => implode("\n", $actionsList),
			];

		// Send an email to the user
		if ($emailUser)
		{
			Log::add("Emailing the user", Log::DEBUG, 'com_datacompliance');

			try
			{
				TemplateEmails::sendMail('com_datacompliance.user_' . $type, $emailVariables, $user);
			}
			catch (Exception $e)
			{
				// Well, it looks like Joomla failed to send the email.
			}
		}

		// Send the admin emails
		if (!$emailAdmin)
		{
			return;
		}

		Log::add("Emailing the admins", Log::DEBUG, 'com_datacompliance');

		$adminEmails = trim($this->params->get('adminemails', ''));
		$adminEmails = explode("\n", $adminEmails);
		$adminEmails = empty($adminEmails) ? [] : array_map('trim', $adminEmails);
		$superUsers  = $this->getSuperUserEmails($adminEmails);

		if (empty($superUsers))
		{
			return;
		}

		foreach ($superUsers as $sa)
		{
			$emailVariables = array_merge($emailVariables, [
				'admin:name'     => $sa->name,
				'admin:username' => $sa->username,
				'admin:email'    => $sa->email,
			]);

			try
			{
				TemplateEmails::sendMail('com_datacompliance.admin_' . $type, $emailVariables, $sa);
			}
			catch (Exception $e)
			{
				// Well, it looks like Joomla failed to send the email.
			}
		}
	}

	/**
	 * Returns the Super Users email information. If you provide an array $email with a list of addresses we will check
	 * that these emails do belong to Super Users and that they have not blocked system emails.
	 *
	 * @param   array  $email  A list of Super Users to email
	 *
	 * @return  array  The list of Super User emails
	 */
	private function getSuperUserEmails(array $email = []): array
	{
		// Get a reference to the database object
		$db = $this->getDatabase();

		// Convert the email list to an array
		if (empty($email))
		{
			$emails = [];
		}

		// Get a list of groups which have Super User privileges
		$ret = [];

		try
		{
			$q      = $db->getQuery(true)
				->select([$db->quoteName('id')])
				->from($db->quoteName('#__usergroups'));
			$groups = $db->setQuery($q)->loadColumn();

			// Get the groups that are Super Users
			$groups = array_filter($groups, function ($gid) {
				return Access::checkGroup($gid, 'core.admin');
			});

			if (empty($groups))
			{
				return $ret;
			}
		}
		catch (Exception $exc)
		{
			return $ret;
		}

		// Get the user IDs of users belonging to the SA groups
		try
		{
			$query = $db->getQuery(true)
				->select($db->quoteName('user_id'))
				->from($db->quoteName('#__user_usergroup_map'))
				->whereIn($db->quoteName('group_id'), $groups, ParameterType::INTEGER);
			$db->setQuery($query);
			$rawUserIDs = $db->loadColumn(0);

			if (empty($rawUserIDs))
			{
				return $ret;
			}

			$userIDs = [];

			foreach ($rawUserIDs as $id)
			{
				$userIDs[] = $db->q($id);
			}
		}
		catch (Exception $exc)
		{
			return $ret;
		}

		// Get the user information for the Super Administrator users
		try
		{
			$query = $db->getQuery(true)
				->select([
					$db->quoteName('id'),
					$db->quoteName('username'),
					$db->quoteName('name'),
					$db->quoteName('email'),
				])->from($db->quoteName('#__users'))
				->whereIn($db->quoteName('id'), $userIDs, ParameterType::INTEGER)
				->where($db->quoteName('sendEmail') . ' = 1');

			if (!empty($emails))
			{
				$query->whereIn($db->quoteName('email'), $email, ParameterType::INTEGER);
			}

			$db->setQuery($query);
			$ret = $db->loadObjectList();
		}
		catch (Exception $exc)
		{
			return $ret;
		}

		return $ret;
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