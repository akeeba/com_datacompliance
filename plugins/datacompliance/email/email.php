<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\DataCompliance\Admin\Helper\Email;
use Akeeba\DataCompliance\Site\Model\Options;
use FOF40\Container\Container;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Log\Log;

defined('_JEXEC') or die;

/**
 * Data Compliance plugin for Akeeba Ticket System User Data
 */
class plgDatacomplianceEmail extends Joomla\CMS\Plugin\CMSPlugin
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
	public function __construct($subject, array $config = [])
	{
		$this->autoloadLanguage = true;
		$this->container        = Container::getInstance('com_datacompliance');

		parent::__construct($subject, $config);
	}

	/**
	 * Performs the necessary actions for deleting a user.
	 *
	 * Sends emails to the user and the administrator.
	 *
	 * @param   int     $userID  The user ID we are asked to delete
	 * @param   string  $type    The export type (user, admin, lifecycle)
	 *
	 * @return  array
	 */
	public function onDataComplianceDeleteUser(int $userID, string $type): array
	{
		if ($this->container->platform->getSessionVar('__audit_replay', 0, 'com_datacompliance'))
		{
			Log::add("Will NOT send email", Log::DEBUG, 'com_datacompliance');

			return [];
		}

		$emailUser  = $this->params->get('users', 1);
		$emailAdmin = $this->params->get('admins', 1);

		// Check if we have to send any emails
		if (!$emailUser && !$emailAdmin)
		{
			return [];
		}

		Log::add("Sending email about deleting user #$userID, type ‘{$type}’", Log::INFO, 'com_datacompliance');

		// Get the actions
		$user = $this->container->platform->getUser($userID);

		// Get the actions carried out for the user
		/** @var Options $optionsModel */
		$optionsModel = $this->container->factory->model('Options')->tmpInstance();
		$actionsList  = $optionsModel->getBulletPoints($user, $type);
		$actionsHtml  = "<ul>\n";

		foreach ($actionsList as $action)
		{
			$actionsHtml .= "<li>$action</li>";
		}

		$actionsHtml .= "</ul>\n";

		$extras = [
			'[ACTIONS]' => $actionsHtml,
		];

		// Send an email to the user
		if ($emailUser)
		{
			Log::add("Emailing the user", Log::DEBUG, 'com_datacompliance');

			try
			{
				$mailer = Email::getPreloadedMailer('user_' . $type, $userID, $extras);

				if ($mailer !== false)
				{
					$mailer->addRecipient($user->email, $user->name);
					$mailer->Send();
				}
			}
			catch (Exception $e)
			{
				// Well, it looks like Joomla failed to send the email.
			}
		}

		// Send the admin emails
		if ($emailAdmin)
		{
			Log::add("Emailing the admins", Log::DEBUG, 'com_datacompliance');

			$adminEmails = trim($this->params->get('adminemails', ''));
			$adminEmails = explode("\n", $adminEmails);
			$adminEmails = empty($adminEmails) ? [] : array_map('trim', $adminEmails);

			$superUsers = $this->getSuperUserEmails($adminEmails);

			if (empty($superUsers))
			{
				return [];
			}

			foreach ($superUsers as $sa)
			{
				$newExtras = array_merge($extras, [
					'[ADMIN:NAME]'     => $sa->name,
					'[ADMIN:USERNAME]' => $sa->username,
					'[ADMIN:EMAIL]'    => $sa->email,
				]);

				try
				{
					$mailer = Email::getPreloadedMailer('admin_' . $type, $userID, $newExtras);

					if ($mailer !== false)
					{
						$mailer->addRecipient($sa->email, $sa->name);
						$mailer->Send();
					}

				}
				catch (Exception $e)
				{
					// Well, it looks like Joomla failed to send the email.
				}
			}
		}

		// Finally, return an empty array
		return [];
	}

	/**
	 * Returns the Super Users email information. If you provide an array $email with a list of addresses we will check
	 * that these emails do belong to Super Users and that they have not blocked system emails.
	 *
	 * @param   array  $email  A list of Super Users to email
	 *
	 * @return  array  The list of Super User emails
	 */
	private function getSuperUserEmails(array $email = [])
	{
		// Get a reference to the database object
		$db = JFactory::getDbo();

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
				->select([$db->qn('id')])
				->from($db->qn('#__usergroups'));
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
				->select($db->qn('user_id'))
				->from($db->qn('#__user_usergroup_map'))
				->where($db->qn('group_id') . ' IN(' . implode(',', $groups) . ')');
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
					$db->qn('id'),
					$db->qn('username'),
					$db->qn('name'),
					$db->qn('email'),
				])->from($db->qn('#__users'))
				->where($db->qn('id') . ' IN(' . implode(',', $userIDs) . ')')
				->where($db->qn('sendEmail') . ' = ' . $db->q('1'));

			if (!empty($emails))
			{
				$query->where($db->qn('email') . 'IN(' . implode(',', $emails) . ')');
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

}