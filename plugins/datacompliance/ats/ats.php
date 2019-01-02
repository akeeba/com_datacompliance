<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\DataCompliance\Admin\Helper\Export;
use FOF30\Container\Container;
use Joomla\CMS\Log\Log;

defined('_JEXEC') or die;

if (!include_once (JPATH_ADMINISTRATOR . '/components/com_datacompliance/assets/plugin/AbstractPlugin.php'))
{
	return;
}

/**
 * Data Compliance plugin for Akeeba Ticket System User Data
 */
class plgDatacomplianceAts extends plgDatacomplianceAbstractPlugin
{
	/**
	 * Performs the necessary actions for deleting a user. Returns an array of the information categories and any
	 * applicable IDs which were deleted in the process. This information is stored in the audit log. DO NOT include
	 * any personally identifiable information.
	 *
	 * This plugin takes the following actions:
	 * - Delete ATS tickets (and posts and attachments) related to the user
	 *
	 * @param   int    $userID The user ID we are asked to delete
	 * @param   string $type   The export type (user, admin, lifecycle)
	 *
	 * @return  array
	 */
	public function onDataComplianceDeleteUser(int $userID, string $type): array
	{
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

		Log::add("Deleting user #$userID, type ‘{$type}’, Akeeba Ticket System data", Log::INFO, 'com_datacompliance');
		Log::add(sprintf('ATS -- RAM %s', $this->memUsage()), Log::INFO, 'com_datacompliance.memory');

		$container = Container::getInstance('com_ats', [], 'admin');
		$db        = $container->db;
		$db->setDebug(false);

		// ============================== tickets, posts, attachments ==============================

		// Query for the ticket IDs
		$ticketsQuery = $db->getQuery(true)
			->select($db->qn('ats_ticket_id'))
			->from($db->qn('#__ats_tickets'))
			->where($db->qn('created_by') . ' = ' . $userID);

		if ($type == 'lifecycle')
		{
			$ticketsQuery->where($db->qn('public') . ' = 0');
		}

		$ticketIDs             = $db->setQuery($ticketsQuery)->loadColumn(0);
		$ret['ats']['tickets'] = $ticketIDs;
		$postIDs               = [];

		if (!empty($ticketIDs))
		{
			// Query for the post IDs
			$postsQuery = $db->getQuery(true)
				->select($db->qn('ats_post_id'))
				->from($db->qn('#__ats_posts'))
				->where($db->qn('ats_ticket_id') . ' IN (' . implode(',', array_map('intval', $ticketIDs)) . ')');

			$postIDs             = $db->setQuery($postsQuery)->loadColumn(0);
			$ret['ats']['posts'] = $postIDs;
		}

		if (!empty($postIDs))
		{
			// Query for the attachment IDs
			$attachmentsQuery          = $db->getQuery(true)
				->select($db->qn('ats_attachment_id'))
				->from($db->qn('#__ats_attachments'))
				->where($db->qn('ats_post_id') . ' IN(' . implode(',', array_map('intval', $postIDs)) . ')');
			$ret['ats']['attachments'] = $db->setQuery($attachmentsQuery)->loadColumn(0);

			// Delete attachments
			$query = $db->getQuery(true)
				->delete($db->qn('#__ats_attachments'))
				->where($db->qn('ats_post_id') . ' IN(' . implode(',', array_map('intval', $postIDs)) . ')');
			$db->setQuery($query)->execute();
			unset($postIDs);

			// Delete posts
			$query = $db->getQuery(true)
				->delete($db->qn('#__ats_posts'))
				->where($db->qn('ats_ticket_id') . ' IN (' . implode(',', array_map('intval', $ticketIDs)) . ')');
			$db->setQuery($query)->execute();
		}

		// Delete tickets
		if (!empty($ticketIDs))
		{
			$query = $db->getQuery(true)
				->delete($db->qn('#__ats_tickets'))
				->where($db->qn('ats_ticket_id') . ' IN (' . implode(',', array_map('intval', $ticketIDs)) . ')');
			$db->setQuery($query)->execute();

			// ============================== attempts ==============================
			$query                  = $db->getQuery(true)
				->select($db->qn('ats_attempt_id'))
				->from($db->qn('#__ats_attempts'))
				->where($db->qn('ats_ticket_id') . ' IN (' . implode(',', array_map('intval', $ticketIDs)) . ')');
			$ret['ats']['attempts'] = $db->setQuery($query)->loadColumn();

			$query = $db->getQuery(true)
				->delete($db->qn('#__ats_attempts'))
				->where($db->qn('ats_ticket_id') . ' IN (' . implode(',', array_map('intval', $ticketIDs)) . ')');
			$db->setQuery($query)->execute();
		}

		unset($ticketIDs);

		// ============================== creditconsumptions ==============================
		$query                            = $db->getQuery(true)
			->select($db->qn('ats_creditconsumption_id'))
			->from($db->qn('#__ats_creditconsumptions'))
			->where($db->qn('user_id') . ' = ' . $userID);
		$ret['ats']['creditconsumptions'] = $db->setQuery($query)->loadColumn();

		if (!empty($ret['ats']['creditconsumptions']))
		{
			$query = $db->getQuery(true)
				->delete($db->qn('#__ats_creditconsumptions'))
				->where($db->qn('user_id') . ' = ' . $userID);
			$db->setQuery($query)->execute();
		}

		// ============================== credittransactions ==============================
		$query                            = $db->getQuery(true)
			->select($db->qn('ats_credittransaction_id'))
			->from($db->qn('#__ats_credittransactions'))
			->where($db->qn('user_id') . ' = ' . $userID);
		$ret['ats']['credittransactions'] = $db->setQuery($query)->loadColumn();

		if (!empty($ret['ats']['credittransactions']))
		{
			$query = $db->getQuery(true)
				->delete($db->qn('#__ats_credittransactions'))
				->where($db->qn('user_id') . ' = ' . $userID);
			$db->setQuery($query)->execute();
		}

		// ============================== usertags ==============================
		$query                  = $db->getQuery(true)
			->select($db->qn('id'))
			->from($db->qn('#__ats_users_usertags'))
			->where($db->qn('user_id') . ' = ' . $userID);
		$ret['ats']['usertags'] = $db->setQuery($query)->loadColumn();

		if (!empty($ret['ats']['usertags']))
		{
			$query = $db->getQuery(true)
				->delete($db->qn('#__ats_users_usertags'))
				->where($db->qn('user_id') . ' = ' . $userID);
			$db->setQuery($query)->execute();
		}

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
			JText::_('PLG_DATACOMPLIANCE_ATS_ACTIONS_1'),
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
		$export = new SimpleXMLElement("<root></root>");

		// Tickets
		$domainTickets = $export->addChild('domain');
		$domainTickets->addAttribute('name', 'ats_tickets');
		$domainTickets->addAttribute('description', 'Akeeba Ticket System tickets');

		$tickets = $this->getTickets($userID);
		$ticketIDs = [];

		array_map(function($ticket) use ($domainTickets, &$ticketIDs) {
			Export::adoptChild($domainTickets, Export::exportItemFromObject($ticket));
			$ticketIDs[] = $ticket->ats_ticket_id;
		}, $tickets);
		unset($tickets);

		// Export #__ats_attempts entries
		$domain = $export->addChild('domain');
		$domain->addAttribute('name', 'ats_attempts');
		$domain->addAttribute('description', 'Akeeba Ticket System ticket filing attempts (successful), linked to each ticket');

		if (!empty($ticketIDs))
		{
			$db = $this->container->db;
			$selectQuery = $db->getQuery(true)
				->select('*')
				->from($db->qn('#__ats_attempts'))
				->where($db->qn('ats_ticket_id') . ' IN(' . implode(',', array_map('intval', $ticketIDs)) . ')');

			$items = $db->setQuery($selectQuery)->loadObjectList();

			array_map(function($item) {
				Export::adoptChild($domainAttachments, Export::exportItemFromObject($item));
			}, $items);
		}

		// Posts
		$domainPosts = $export->addChild('domain');
		$domainPosts->addAttribute('name', 'ats_posts');
		$domainPosts->addAttribute('description', 'Akeeba Ticket System posts, linked to each ticket');

		$posts = $this->getPosts($ticketIDs);
		$postIDs = [];

		array_map(function($post) use ($domainPosts, &$postIDs) {
			Export::adoptChild($domainPosts, Export::exportItemFromObject($post));
			$postIDs[] = $post->ats_post_id;
		}, $posts);

		unset($ticketIDs);
		unset($posts);


		// Attachments
		$domainAttachments = $export->addChild('domain');
		$domainAttachments->addAttribute('name', 'ats_attachments');
		$domainAttachments->addAttribute('description', 'Akeeba Ticket System attachments, linked to each post');

		$attachments = $this->getAttachments($postIDs);

		array_map(function($attachment) use ($domainAttachments) {
			Export::adoptChild($domainAttachments, Export::exportItemFromObject($attachment));
			$postIDs[] = $attachment->ats_post_id;
		}, $attachments);

		// Export #__ats_creditconsumptions entries
		$domain = $export->addChild('domain');
		$domain->addAttribute('name', 'ats_creditconsumptions');
		$domain->addAttribute('description', 'Akeeba Ticket System credit consumption events, linked to each ticket');

		$db = $this->container->db;
		$selectQuery = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__ats_creditconsumptions'))
			->where($db->qn('user_id') . ' = ' . (int)$userID);

		$items = $db->setQuery($selectQuery)->loadObjectList();

		array_map(function($item) {
			Export::adoptChild($domainAttachments, Export::exportItemFromObject($item));
		}, $items);

		// Export #__ats_credittransactions entries
		$domain = $export->addChild('domain');
		$domain->addAttribute('name', 'ats_credittransactions');
		$domain->addAttribute('description', 'Akeeba Ticket System credit transactions (credit purchases)');

		$db = $this->container->db;
		$selectQuery = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__ats_credittransactions'))
			->where($db->qn('user_id') . ' = ' . (int)$userID);

		$items = $db->setQuery($selectQuery)->loadObjectList();

		array_map(function($item) {
			Export::adoptChild($domainAttachments, Export::exportItemFromObject($item));
		}, $items);

		// Export #__ats_users_usertags entries
		$domain = $export->addChild('domain');
		$domain->addAttribute('name', 'ats_user_usertags');
		$domain->addAttribute('description', 'Akeeba Ticket System user tags');

		$db = $this->container->db;
		$selectQuery = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__ats_users_usertags'))
			->where($db->qn('user_id') . ' = ' . (int)$userID);

		$items = $db->setQuery($selectQuery)->loadObjectList();

		array_map(function($item) {
			Export::adoptChild($domainAttachments, Export::exportItemFromObject($item));
		}, $items);

		return $export;
	}

	private function getTickets(int $user_id)
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->select('*')
			->from('#__ats_tickets')
			->where($db->qn('created_by') . ' = ' . $user_id);
		return $db->setQuery($query)->loadObjectList();
	}

	private function getPosts(array $ticketIDs)
	{
		if (empty($ticketIDs))
		{
			return [];
		}

		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->select('*')
			->from('#__ats_posts')
			->where($db->qn('ats_ticket_id') . ' IN(' . implode(',', $ticketIDs) . ')');

		return $db->setQuery($query)->loadObjectList();
	}

	private function getAttachments(array $postIDs)
	{
		if (empty($postIDs))
		{
			return [];
		}

		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->select('*')
			->from('#__ats_attachments')
			->where($db->qn('ats_post_id') . ' IN(' . implode(',', $postIDs) . ')');
		return $db->setQuery($query)->loadObjectList();
	}

	private function deleteAttempts($ticketID)
	{
		Log::add("Deleting ticket attempts for ticket #{$ticketID}", Log::DEBUG, 'com_datacompliance');

		$db = $this->container->db;
		$ids         = [];

		$selectQuery = $db->getQuery(true)
			->select($db->qn('ats_attempt_id'))
			->from($db->qn('#__ats_attempts'))
			->where($db->qn('ats_ticket_id') . ' = ' . (int)$ticketID);

		$deleteQuery = $db->getQuery(true)
			->delete($db->qn('#__ats_attempts'))
			->where($db->qn('ats_ticket_id') . ' = ' . (int)$ticketID);

		try
		{
			$ids = $db->setQuery($selectQuery)->loadColumn(0);
			$db->setQuery($deleteQuery)->execute();
		}
		catch (Exception $e)
		{
			Log::add("Could not delete ATS ticket attempts: {$e->getMessage()}", Log::ERROR, 'com_datacompliance');
			Log::add("Debug trace: {$e->getTraceAsString()}", Log::ERROR, 'com_datacompliance');
			// Never mind...
		}

		if (empty($ids))
		{
			$ids = [];
		}

		return $ids;
	}

	private function deleteCreditConsumptions(int $userID): array
	{
		Log::add("Deleting credit consumptions", Log::DEBUG, 'com_datacompliance');

		$db = $this->container->db;
		$ids         = [];

		$selectQuery = $db->getQuery(true)
			->select($db->qn('ats_creditconsumption_id'))
			->from($db->qn('#__ats_creditconsumptions'))
			->where($db->qn('user_id') . ' = ' . (int)$userID);

		$deleteQuery = $db->getQuery(true)
			->delete($db->qn('#__ats_creditconsumptions'))
			->where($db->qn('user_id') . ' = ' . (int)$userID);

		try
		{
			$ids = $db->setQuery($selectQuery)->loadColumn(0);
			$db->setQuery($deleteQuery)->execute();
		}
		catch (Exception $e)
		{
			Log::add("Could not delete ATS credit consumptions: {$e->getMessage()}", Log::ERROR, 'com_datacompliance');
			Log::add("Debug trace: {$e->getTraceAsString()}", Log::ERROR, 'com_datacompliance');
			// Never mind...
		}

		if (empty($ids))
		{
			return [];
		}

		if (empty($ids))
		{
			return [];
		}

		return $ids;
	}

	private function deleteCreditTransactions(int $userID): array
	{
		Log::add("Deleting credit transactions", Log::DEBUG, 'com_datacompliance');

		$db = $this->container->db;
		$ids         = [];

		$selectQuery = $db->getQuery(true)
			->select($db->qn('ats_credittransaction_id'))
			->from($db->qn('#__ats_credittransactions'))
			->where($db->qn('user_id') . ' = ' . (int)$userID);

		$deleteQuery = $db->getQuery(true)
			->delete($db->qn('#__ats_credittransactions'))
			->where($db->qn('user_id') . ' = ' . (int)$userID);

		try
		{
			$ids = $db->setQuery($selectQuery)->loadColumn(0);
			$db->setQuery($deleteQuery)->execute();
		}
		catch (Exception $e)
		{
			Log::add("Could not delete ATS credit transactions: {$e->getMessage()}", Log::ERROR, 'com_datacompliance');
			Log::add("Debug trace: {$e->getTraceAsString()}", Log::ERROR, 'com_datacompliance');
			// Never mind...
		}

		if (empty($ids))
		{
			return [];
		}

		return $ids;
	}

	private function deleteUserTags(int $userID): array
	{
		Log::add("Deleting ATS user tags", Log::DEBUG, 'com_datacompliance');

		$db = $this->container->db;
		$ids         = [];

		$selectQuery = $db->getQuery(true)
			->select($db->qn('id'))
			->from($db->qn('#__ats_users_usertags'))
			->where($db->qn('user_id') . ' = ' . (int)$userID);

		$deleteQuery = $db->getQuery(true)
			->delete($db->qn('#__ats_users_usertags'))
			->where($db->qn('user_id') . ' = ' . (int)$userID);

		try
		{
			$ids = $db->setQuery($selectQuery)->loadColumn(0);
			$db->setQuery($deleteQuery)->execute();
		}
		catch (Exception $e)
		{
			Log::add("Could not delete ATS user tags: {$e->getMessage()}", Log::ERROR, 'com_datacompliance');
			Log::add("Debug trace: {$e->getTraceAsString()}", Log::ERROR, 'com_datacompliance');
			// Never mind...
		}

		if (empty($ids))
		{
			return [];
		}

		return $ids;
	}
}