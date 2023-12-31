<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\DataCompliance\ATS\Extension;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Helper\Export;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use SimpleXMLElement;

/**
 * Data Compliance plugin for Akeeba Ticket System User Data
 *
 * @since  1.0.0
 */
class ATS extends CMSPlugin implements SubscriberInterface
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
			'onDataComplianceDeleteUser'          => 'onDataComplianceDeleteUser',
			'onDataComplianceExportUser'          => 'onDataComplianceExportUser',
			'onDataComplianceGetWipeBulletpoints' => 'onDataComplianceGetWipeBulletpoints',
		];
	}

	/**
	 * Performs the necessary actions for deleting a user. Returns an array of the information categories and any
	 * applicable IDs which were deleted in the process. This information is stored in the audit log. DO NOT include
	 * any personally identifiable information.
	 *
	 * This plugin takes the following actions:
	 * - Delete ARS log entries relevant to the user
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onDataComplianceDeleteUser(Event $event)
	{
		$isATS5OrLater = @is_dir(JPATH_ADMINISTRATOR . '/components/com_ats/services');

		/**
		 * @var int    $userId The user ID we are asked to delete
		 * @var string $type   The export type (user, admin, lifecycle)
		 */
		[$userId, $type] = array_values($event->getArguments());

		$ret = [
			'ats' => [
				'tickets'            => [],
				'posts'              => [],
				'attachments'        => [],
				'attempts'           => [],
				'creditconsumptions' => [],
				'credittransactions' => [],
				'usertags'           => [],
			],
		];

		Log::add("Deleting user #$userId, type ‘{$type}’, Akeeba Ticket System data", Log::INFO, 'com_datacompliance');

		$db = $this->getDatabase();
		$db->setMonitor(null);

		// ============================== tickets, posts, attachments ==============================

		// Query for the ticket IDs
		$ticketsQuery = $db->getQuery(true)
		                   ->select($db->quoteName($isATS5OrLater ? 'id' : 'ats_ticket_id'))
		                   ->from($db->quoteName('#__ats_tickets'))
		                   ->where($db->qn('created_by') . ' = ' . $db->quote($userId));

		if ($type == 'lifecycle')
		{
			$ticketsQuery->where($db->quoteName('public') . ' = 0');
		}

		$ticketIDs             = $db->setQuery($ticketsQuery)->loadColumn(0);
		$ret['ats']['tickets'] = $ticketIDs;
		$postIDs               = [];

		if (!empty($ticketIDs))
		{
			// Query for the post IDs
			$postsQuery = $db->getQuery(true)
			                 ->select($db->quoteName($isATS5OrLater ? 'id' : 'ats_post_id'))
			                 ->from($db->quoteName('#__ats_posts'))
			                 ->whereIn($db->quoteName($isATS5OrLater ? 'ticket_id' : 'ats_ticket_id'), $ticketIDs, ParameterType::INTEGER);

			$postIDs             = $db->setQuery($postsQuery)->loadColumn(0);
			$ret['ats']['posts'] = $postIDs;
		}

		if (!empty($postIDs))
		{
			// Query for the attachment IDs
			$attachmentsQuery          = $db->getQuery(true)
			                                ->select($db->quoteName($isATS5OrLater ? 'id' : 'ats_attachment_id'))
			                                ->from($db->quoteName('#__ats_attachments'))
			                                ->whereIn($db->quoteName($isATS5OrLater ? 'post_id' : 'ats_post_id'), $postIDs, ParameterType::INTEGER);
			$ret['ats']['attachments'] = $db->setQuery($attachmentsQuery)->loadColumn(0);

			// Delete attachments
			$query = $db->getQuery(true)
			            ->delete($db->quoteName('#__ats_attachments'))
			            ->whereIn($db->quoteName($isATS5OrLater ? 'post_id' : 'ats_post_id'), $postIDs, ParameterType::INTEGER);
			$db->setQuery($query)->execute();
			unset($postIDs);

			// Delete posts
			$query = $db->getQuery(true)
			            ->delete($db->quoteName('#__ats_posts'))
			            ->whereIn($db->quoteName($isATS5OrLater ? 'ticket_id' : 'ats_ticket_id'), $ticketIDs, ParameterType::INTEGER);
			$db->setQuery($query)->execute();
		}

		// Delete tickets
		if (!empty($ticketIDs))
		{
			$query = $db->getQuery(true)
			            ->delete($db->quoteName('#__ats_tickets'))
			            ->whereIn($db->quoteName($isATS5OrLater ? 'id' : 'ats_ticket_id'), $ticketIDs, ParameterType::INTEGER);
			$db->setQuery($query)->execute();

			// ============================== attempts ==============================
			try
			{
				$query = $db->getQuery(true)
				            ->select($db->quoteName('ats_attempt_id'))
				            ->from($db->quoteName('#__ats_attempts'))
				            ->whereIn($db->quoteName('ats_ticket_id'), $ticketIDs, ParameterType::INTEGER);

				$ret['ats']['attempts'] = $db->setQuery($query)->loadColumn();

				$query = $db->getQuery(true)
				            ->delete($db->quoteName('#__ats_attempts'))
				            ->whereIn($db->quoteName('ats_ticket_id'), $ticketIDs, ParameterType::INTEGER);
				$db->setQuery($query)->execute();
			}
			catch (\Exception $e)
			{
				unset($ret['ats']['attempts']);
			}
		}

		unset($ticketIDs);

		// ============================== creditconsumptions ==============================

		try
		{
			$query = $db->getQuery(true)
			            ->select($db->quoteName('ats_creditconsumption_id'))
			            ->from($db->quoteName('#__ats_creditconsumptions'))
			            ->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));

			$ret['ats']['creditconsumptions'] = $db->setQuery($query)->loadColumn();

			if (!empty($ret['ats']['creditconsumptions']))
			{
				$query = $db->getQuery(true)
				            ->delete($db->quoteName('#__ats_creditconsumptions'))
				            ->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));

				$db->setQuery($query)->execute();
			}
		}
		catch (\Exception $e)
		{
			unset($ret['ats']['creditconsumptions']);
		}

		// ============================== credittransactions ==============================

		try
		{
			$query = $db->getQuery(true)
			            ->select($db->quoteName('ats_credittransaction_id'))
			            ->from($db->quoteName('#__ats_credittransactions'))
			            ->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));

			$ret['ats']['credittransactions'] = $db->setQuery($query)->loadColumn();

			if (!empty($ret['ats']['credittransactions']))
			{
				$query = $db->getQuery(true)
				            ->delete($db->quoteName('#__ats_credittransactions'))
				            ->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));

				$db->setQuery($query)->execute();
			}
		}
		catch (\Exception $e)
		{
			unset($ret['ats']['credittransactions']);
		}

		// ============================== usertags ==============================
		try
		{
			$query = $db->getQuery(true)
			            ->select($db->quoteName('id'))
			            ->from($db->quoteName('#__ats_users_usertags'))
			            ->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));

			$ret['ats']['usertags'] = $db->setQuery($query)->loadColumn();

			if (!empty($ret['ats']['usertags']))
			{
				$query = $db->getQuery(true)
				            ->delete($db->quoteName('#__ats_users_usertags'))
				            ->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));

				$db->setQuery($query)->execute();
			}
		}
		catch (\Exception $e)
		{
			unset ($ret['ats']['usertags']);
		}

		$this->setEventResult($event, $ret);
	}

	/**
	 * Used for exporting the user information in XML format. The returned data is a SimpleXMLElement document with a
	 * data dump following the structure root > domain > item[...] > column[...].
	 *
	 * This plugin exports the following tables / models:
	 * - #__ars_log
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onDataComplianceExportUser(Event $event): void
	{
		$isATS5OrLater = @is_dir(JPATH_ADMINISTRATOR . '/components/com_ats/services');

		/** @var int $userId */
		[$userId] = array_values($event->getArguments());

		$export = new SimpleXMLElement("<root></root>");

		// Tickets
		$domainTickets = $export->addChild('domain');
		$domainTickets->addAttribute('name', 'ats_tickets');
		$domainTickets->addAttribute('description', 'Akeeba Ticket System tickets');

		$tickets   = $this->getTickets($userId);
		$ticketIDs = [];

		array_map(function ($ticket) use (&$domainTickets, &$ticketIDs, $isATS5OrLater) {
			Export::adoptChild($domainTickets, Export::exportItemFromObject($ticket));
			$ticketIDs[] = $isATS5OrLater ? $ticket->id : $ticket->ats_ticket_id;
		}, $tickets);
		unset($tickets);

		// Export #__ats_attempts entries
		if (!empty($ticketIDs))
		{
			try
			{
				$db          = $this->getDatabase();
				$selectQuery = $db->getQuery(true)
				                  ->select('*')
				                  ->from($db->quoteName('#__ats_attempts'))
				                  ->whereIn($db->quoteName('ats_ticket_id'), $ticketIDs, ParameterType::INTEGER);

				$items = $db->setQuery($selectQuery)->loadObjectList();

				$domain = $export->addChild('domain');
				$domain->addAttribute('name', 'ats_attempts');
				$domain->addAttribute('description', 'Akeeba Ticket System ticket filing attempts (successful), linked to each ticket');

				array_map(function ($item) use (&$domainAttachments) {
					Export::adoptChild($domainAttachments, Export::exportItemFromObject($item));
				}, $items);
			}
			catch (\Exception $e)
			{
			}
		}

		// Posts
		$domainPosts = $export->addChild('domain');
		$domainPosts->addAttribute('name', 'ats_posts');
		$domainPosts->addAttribute('description', 'Akeeba Ticket System posts, linked to each ticket');

		$posts   = $this->getPosts($ticketIDs);
		$postIDs = [];

		array_map(function ($post) use (&$domainPosts, &$postIDs, $isATS5OrLater) {
			Export::adoptChild($domainPosts, Export::exportItemFromObject($post));
			$postIDs[] = $isATS5OrLater ? $post->id : $post->ats_post_id;
		}, $posts);

		unset($ticketIDs);
		unset($posts);


		// Attachments
		$domainAttachments = $export->addChild('domain');
		$domainAttachments->addAttribute('name', 'ats_attachments');
		$domainAttachments->addAttribute('description', 'Akeeba Ticket System attachments, linked to each post');

		$attachments = $this->getAttachments($postIDs);

		array_map(function ($attachment) use (&$domainAttachments, $isATS5OrLater) {
			Export::adoptChild($domainAttachments, Export::exportItemFromObject($attachment));
			$postIDs[] = $isATS5OrLater ? $attachment->id : $attachment->ats_post_id;
		}, $attachments);

		// Export #__ats_creditconsumptions entries
		try
		{
			$db          = $this->getDatabase();
			$selectQuery = $db->getQuery(true)
			                  ->select('*')
			                  ->from($db->quoteName('#__ats_creditconsumptions'))
			                  ->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));

			$items = $db->setQuery($selectQuery)->loadObjectList();

			$domain = $export->addChild('domain');
			$domain->addAttribute('name', 'ats_creditconsumptions');
			$domain->addAttribute('description', 'Akeeba Ticket System credit consumption events, linked to each ticket');

			array_map(function ($item) use (&$domainAttachments) {
				Export::adoptChild($domainAttachments, Export::exportItemFromObject($item));
			}, $items);
		}
		catch (\Exception $e)
		{
		}

		// Export #__ats_credittransactions entries
		try
		{
			$db          = $this->getDatabase();
			$selectQuery = $db->getQuery(true)
			                  ->select('*')
			                  ->from($db->quoteName('#__ats_credittransactions'))
			                  ->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));

			$items = $db->setQuery($selectQuery)->loadObjectList();

			$domain = $export->addChild('domain');
			$domain->addAttribute('name', 'ats_credittransactions');
			$domain->addAttribute('description', 'Akeeba Ticket System credit transactions (credit purchases)');

			array_map(function ($item) use (&$domainAttachments) {
				Export::adoptChild($domainAttachments, Export::exportItemFromObject($item));
			}, $items);
		}
		catch (\Exception $e)
		{

		}

		// Export #__ats_users_usertags entries
		try
		{
			$db          = $this->getDatabase();
			$selectQuery = $db->getQuery(true)
			                  ->select('*')
			                  ->from($db->quoteName('#__ats_users_usertags'))
			                  ->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));

			$items = $db->setQuery($selectQuery)->loadObjectList();

			$domain = $export->addChild('domain');
			$domain->addAttribute('name', 'ats_user_usertags');
			$domain->addAttribute('description', 'Akeeba Ticket System user tags');

			array_map(function ($item) use (&$domainAttachments) {
				Export::adoptChild($domainAttachments, Export::exportItemFromObject($item));
			}, $items);

			$this->setEventResult($event, $export);
		}
		catch (\Exception $e)
		{

		}
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
		[$userId, $type] = array_values($event->getArguments());

		$this->setEventResult($event, [
			Text::_('PLG_DATACOMPLIANCE_ATS_ACTIONS_1'),
		]);
	}

	private function getAttachments(array $postIDs)
	{
		if (empty($postIDs))
		{
			return [];
		}

		$isATS5OrLater = @is_dir(JPATH_ADMINISTRATOR . '/components/com_ats/services');

		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
		            ->select('*')
		            ->from('#__ats_attachments')
		            ->whereIn($db->quoteName($isATS5OrLater ? 'post_id' : 'ats_post_id'), $postIDs, ParameterType::INTEGER);

		return $db->setQuery($query)->loadObjectList();
	}

	private function getPosts(array $ticketIDs)
	{
		if (empty($ticketIDs))
		{
			return [];
		}

		$isATS5OrLater = @is_dir(JPATH_ADMINISTRATOR . '/components/com_ats/services');

		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
		            ->select('*')
		            ->from('#__ats_posts')
		            ->whereIn($db->quoteName($isATS5OrLater ? 'ticket_id' : 'ats_ticket_id'), $ticketIDs, ParameterType::INTEGER);

		return $db->setQuery($query)->loadObjectList();
	}

	private function getTickets(int $user_id)
	{
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
		            ->select('*')
		            ->from('#__ats_tickets')
		            ->where($db->quoteName('created_by') . ' = ' . $db->quote($user_id));

		return $db->setQuery($query)->loadObjectList();
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