<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\DataCompliance\Admin\Helper\Export;
use Akeeba\Subscriptions\Admin\Model\Subscriptions;
use FOF30\Container\Container;
use Joomla\CMS\Log\Log;

defined('_JEXEC') or die;

/**
 * Data Compliance plugin for Akeeba Release System User Data
 */
class plgDatacomplianceAkeebasubs extends Joomla\CMS\Plugin\CMSPlugin
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
	public function __construct($subject, array $config = array())
	{
		$this->autoloadLanguage = true;
		$this->container = \FOF30\Container\Container::getInstance('com_datacompliance');

		parent::__construct($subject, $config);
	}

	/**
	 * Checks whether a user is safe to be deleted. This plugin prevents deletion on the following conditions:
	 * - The user has an active subscription created within the last X days (which means it's likely not yet reported for tax purposes)
	 *
	 * @param   int     $userID  The user ID we are asked for permission to delete
	 * @param   string  $type    user, admin or lifecycle
	 *
	 * @return  void  No return value is expected. Throw exceptions when there is a problem.
	 *
	 * @throws  RuntimeException  The error which prevents us from deleting a user
	 */
	public function onDataComplianceCanDelete($userID, $type)
	{
		$container = Container::getInstance('com_akeebasubs', [], 'admin');

		/** @var Subscriptions $subs */
		$subs = $container->factory->model('Subscriptions')->tmpInstance();
		$subs->user_id($userID)->paystate(['C']);

		/**
		 * On non-lifecycle deletion we only check for susbcriptions created within the last $period days.
		 *
		 * Corollary: on lifecycle deletion, any active subscription prevents deletion (since you're still in a business
		 * relationship with us).
		 */
		if ($type != 'lifecycle')
		{
			// Configurable. The default is incidentally PayPal's maximum period for filing a payment dispute.
			$period = (int) $this->params->get('guard_threshold', 90);

			if ($period < 1)
			{
				return;
			}

			$now = $container->platform->getDate();
			$interval = new DateInterval('P' . (int)$period . 'D');
			$since = $now->sub($interval);
			$subs->since($since->toSql());
		}
		else
		{
			// Lifecycle deletion. Only look for subscriptions expiring after now and are paid for.
			$now = $container->platform->getDate();
			$subs->expires_from($now->toSql());
		}

		$numLatestSubs = $subs->get()->count();
		
		if ($numLatestSubs > 0)
		{
			if ($type != 'lifecycle')
			{
				throw new RuntimeException(JText::sprintf('PLG_DATACOMPLIANCE_AKEEBASUBS_ERR_HASSUBS', $numLatestSubs, $period));
			}

			throw new RuntimeException(JText::sprintf('PLG_DATACOMPLIANCE_AKEEBASUBS_ERR_HASSUBS_GENERAL', $numLatestSubs));
		}
	}

	/**
	 * Performs the necessary actions for deleting a user. Returns an array of the information categories and any
	 * applicable IDs which were deleted in the process. This information is stored in the audit log. DO NOT include
	 * any personally identifiable information.
	 *
	 * This plugin takes the following actions:
	 * - Remove all subscriptions with paystate "N" (failed transactions).
	 * - Modify subscriptions with a paystate "C" or "X" with payment processor "DATA_COMPLIANCE_WIPED" and a random
	 *   unique ID prefixed by the deletion date/time stamp e.g. "20180420-103200-dfawey2h24t2tnlwhfwngym0024245. Remove
	 *   the IP, country and user agent information from these records. Replace the notes with "This record has been
	 *   pseudonymized per GDPR requirements".
	 * - Remove all user information.
	 * - Remove all invoice and credit note information.
	 *
	 * @param   int    $userID The user ID we are asked to delete
	 * @param   string $type   The export type (user, admin, lifecycle)
	 *
	 * @return  array
	 */
	public function onDataComplianceDeleteUser(int $userID, string $type): array
	{
		$ret = [
			'akeebasubs' => [
				'subscriptions_deleted' => [],
				'subscriptions_anonymized' => [],
				'invoices'      => [],
				'creditnotes'   => [],
				'users'         => [],
			],
		];

		Log::add("Deleting user #$userID, type ‘{$type}’, Akeeba Subscriptions data", Log::INFO, 'com_datacompliance');

		/**
		 * Remove invoices and credit notes.
		 *
		 * IMPORTANT! DO NOT CHANGE THE ORDER OF OPERATIONS.
		 *
		 * Credit notes are keyed to invoices. Invoices are keyed to subscriptions. Therefore we need to remove CN
		 * before invoices and only then can we remove subscriptions.
		 *
		 */
		$container = Container::getInstance('com_akeebasubs', [], 'admin');
		$db = $container->db;
		$query = $db->getQuery(true)
			->select($db->qn('akeebasubs_subscription_id'))
			->from($db->qn('#__akeebasubs_subscriptions'))
			->where($db->qn('user_id') . ' = ' . (int) $userID);
		$subIDs = $db->setQuery($query)->loadColumn(0);

		$count = count($subIDs);
		Log::add("Found {$count} subscription(s)", Log::DEBUG, 'com_datacompliance');

		/** @var Subscriptions $sub */
		$sub = $container->factory->model('Subscriptions')->tmpInstance();

		/** @var Subscriptions $sub */
		foreach ($subIDs as $subID)
		{
			try
			{
				$sub->findOrFail($subID);
			}
			catch (Exception $e)
			{
				Log::add("Subscription #$subID has gone away?!", Log::WARNING, 'com_datacompliance');
				continue;
			}

			if (empty($sub))
			{
				continue;
			}

			// Delete credit notes and invoices
			try
			{
				$invoice = $sub->invoice;
			}
			catch (Exception $e)
			{
				Log::add("Subscription #$subID does not have an invoice.", Log::DEBUG, 'com_datacompliance');

				$invoice = null;
			}

			if (!empty($invoice))
			{
				try
				{
					$creditNote = $invoice->creditNote;
				}
				catch (Exception $e)
				{
					$creditNote = null;
					Log::add("Subscription #$subID does not have a credit note.", Log::DEBUG, 'com_datacompliance');
				}

				if (!empty($creditNote))
				{
					Log::add("Deleting credit note #{$creditNote->display_number}.", Log::DEBUG, 'com_datacompliance');

					$ret['akeebasubs']['creditnotes'][] = $creditNote->display_number;
					$creditNote->delete();
				}

				Log::add("Deleting invoice #{$invoice->display_number}.", Log::DEBUG, 'com_datacompliance');
				$ret['akeebasubs']['invoices'][] = $invoice->display_number;
				$invoice->delete();

			}

			// Remove all subscriptions with paystate "N" (failed transactions).
			if ($sub->paystate == 'N')
			{
				Log::add("Deleting UNPAID (state=N) subscription #{$sub->getId()}.", Log::DEBUG, 'com_datacompliance');
				$ret['akeebasubs']['subscriptions_deleted'][] = $sub->getId();
				$sub->delete();

				continue;
			}

			// Anonymize the subscription if its payment state is other than "N".
			Log::add("Anonymizing subscription #{$sub->getId()}.", Log::DEBUG, 'com_datacompliance');
			$ret['akeebasubs']['subscriptions_anonymized'][] = $sub->getId();

			$sub->save([
				'processor'     => 'DATA_COMPLIANCE_WIPED',
				'processor_key' => gmdate('Ymd-His') . '-' . \Joomla\CMS\User\UserHelper::genRandomPassword('24'),
				'ip'            => '',
				'ua'            => '',
				'notes'         => 'This record has been pseudonymized per GDPR requirements',
			]);
		}

		// Remove user information
		$ret['akeebasubs']['users'] = $this->anonymizeUser($userID);


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
			JText::_('PLG_DATACOMPLIANCE_AKEEBASUBS_ACTIONS_1'),
			JText::_('PLG_DATACOMPLIANCE_AKEEBASUBS_ACTIONS_2'),
			JText::_('PLG_DATACOMPLIANCE_AKEEBASUBS_ACTIONS_3'),
			JText::_('PLG_DATACOMPLIANCE_AKEEBASUBS_ACTIONS_4'),
		];
	}

	/**
	 * Used for exporting the user information in XML format. The returned data is a SimpleXMLElement document with a
	 * data dump following the structure root > domain > item[...] > column[...].
	 *
	 * This plugin exports the following tables / models:
	 * - Tickets
	 * - Posts
	 * - Attachments
	 *
	 * @param $userID
	 *
	 * @return SimpleXMLElement
	 */
	public function onDataComplianceExportUser(int $userID): SimpleXMLElement
	{
		$export    = new SimpleXMLElement("<root></root>");
		$container = Container::getInstance('com_akeebasubs');

		// Subscriptions
		$domainSubs = $export->addChild('domain');
		$domainSubs->addAttribute('name', 'akeebasubs_subscriptions');
		$domainSubs->addAttribute('description', 'Akeeba Subscriptions transactions (subscriptions)');

		// Invoices
		$domainInvoices = $export->addChild('domain');
		$domainInvoices->addAttribute('name', 'akeebasubs_invoices');
		$domainInvoices->addAttribute('description', 'Akeeba Subscriptions invoices');

		// Credit Notes
		$domainCreditNotes = $export->addChild('domain');
		$domainCreditNotes->addAttribute('name', 'akeebasubs_creditnotes');
		$domainCreditNotes->addAttribute('description', 'Akeeba Subscriptions credit notes');

		// User Information
		$domainUserInfo = $export->addChild('domain');
		$domainUserInfo->addAttribute('name', 'akeebasubs_users');
		$domainUserInfo->addAttribute('description', 'Akeeba Subscriptions invoicing information');

		/** @var Subscriptions $subsModel */
		$subsModel = $container->factory->model('Subscriptions')->tmpInstance();

		/** @var Subscriptions $sub */
		foreach ($subsModel->user_id($userID)->get(true) as $sub)
		{
			Export::adoptChild($domainSubs, Export::exportItemFromDataModel($sub));

			if (!empty($sub->invoice))
			{
				Export::adoptChild($domainInvoices, Export::exportItemFromDataModel($sub->invoice));
			}

			if (!empty($sub->invoice->creditNote))
			{
				Export::adoptChild($domainCreditNotes, Export::exportItemFromDataModel($sub->invoice->creditNote));
			}
		}

		/** @var \Akeeba\Subscriptions\Admin\Model\Users $user */
		$user = $container->factory->model('Users')->tmpInstance();

		try
		{
			$user->findOrFail(['user_id' => $userID]);

			Export::adoptChild($domainUserInfo, Export::exportItemFromDataModel($user));
		}
		catch (Exception $e)
		{
			// Sometimes we just don't have a record with invoicing information
		}

		return $export;
	}

	/**
	 * Replace the user's personal information with dummy data
	 *
	 * @param   int  $user_id  The user ID we are pseudonymizing
	 *
	 * @return  array  The user ID we pseudonymized
	 */
	private function anonymizeUser($user_id)
	{
		$container = Container::getInstance('com_akeebasubs', [], 'admin');

		/** @var \Akeeba\Subscriptions\Admin\Model\Users $user */
		$user = $container->factory->model('Users')->tmpInstance();

		Log::add("Anonymizing Akeeba Subscriptions user information for user #{$user_id}.", Log::DEBUG, 'com_datacompliance');

		try
		{
			$user->findOrFail(['user_id' => $user_id]);

			$user->save([
				'isbusiness'     => 0,
				'businessname'   => '',
				'occupation'     => '',
				'vatnumber'      => '',
				'viesregistered' => 0,
				'taxauthority'   => '',
				'address1'       => 'Address Redacted',
				'address2'       => '',
				'city'           => 'City Redacted',
				'state'          => '',
				'zip'            => 'REMOVED',
				'country'        => 'XX',
				'params'         => [],
				'notes'          => 'This record has been pseudonymized per GDPR requirements',
				'needs_logout'   => 0,
			]);
		}
		catch (Exception $e)
		{
			Log::add("Could not anonymize Akeeba Subscriptions user #{$user_id}: {$e->getMessage()}", Log::ERROR, 'com_datacompliance');
			Log::add("Debug Backtrace: {$e->getTraceAsString()}", Log::DEBUG, 'com_datacompliance');

			return [];
		}

		return [$user_id];
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
		// Should I run a lifecycle policy based on subscription expiration?
		if (!$this->params->get('lifecycle', 1))
		{
			return [];
		}

		// Get the cutoff date (last subscription expired more than $threshold months ago)
		$threshold      = (int) $this->params->get('threshold', 6);
		$threshold      = min(1, $threshold);
		$jThresholdDate = $this->container->platform->getDate($date)->sub(new DateInterval("P{$threshold}M"));

		$db = $this->container->db;

		// Subquery to get User IDs with active subscriptions
		$jNow     = $this->container->platform->getDate($date);
		$subQuery = $db->getQuery(true)
			->select($db->qn('user_id'))
			->from($db->qn('#__akeebasubs_subscriptions'))
			->where($db->qn('state') . ' = ' . $db->q('C'))
			->where($db->qn('publish_down') . ' > ' . $db->q($jNow->toSql()))
			->group($db->qn('user_id'));

		$query = $db->getQuery(true)
			->select($db->qn('user_id'))
			->from($db->qn('#__akeebasubs_subscriptions'))
			->where($db->qn('state') . ' = ' . $db->q('C'))
			->where($db->qn('publish_down') . ' <= ' . $db->q($jThresholdDate->toSql()))
			->where($db->qn('user_id') . ' NOT IN(' . $subQuery . ')')
			->group($db->qn('user_id'));

		// Remove people already logged into the site in the meantime?
		if ($this->params->get('lastvisit', 1))
		{
			$activeUsersQuery = $db->getQuery(true)
				->select('id')
				->from($db->qn('#__users'))
				->where($db->qn('lastvisitDate') . ' >= ' . $db->q($jThresholdDate->toSql()), 'OR');
			;
			$query->where($db->qn('user_id') . ' NOT IN(' . $activeUsersQuery . ')');
		}

		return $db->setQuery($query)->loadColumn(0);
	}

}