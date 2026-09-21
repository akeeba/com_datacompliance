<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\DataCompliance\ATS\Extension;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Helper\DbQuery;
use Akeeba\Component\DataCompliance\Administrator\Helper\Export;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Filesystem\File;
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
	public function __construct(&$subject, $config = [], ?MVCFactoryInterface $mvcFactory = null)
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
	 * - Delete the user's tickets (only the private ones for lifecycle wipes), their posts, and their attachments,
	 *   including the attachment files
	 * - Delete ATS 4 attempts, credit consumptions, credit transactions and user tags, if these tables exist
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
		$ticketsQuery = DbQuery::create($db)
		                   ->select($db->quoteName($isATS5OrLater ? 'id' : 'ats_ticket_id'))
		                   ->from($db->quoteName('#__ats_tickets'))
		                   ->where($db->qn('created_by') . ' = :userId')
		                   ->bind(':userId', $userId, ParameterType::INTEGER);

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
			$postsQuery = DbQuery::create($db)
			                 ->select($db->quoteName($isATS5OrLater ? 'id' : 'ats_post_id'))
			                 ->from($db->quoteName('#__ats_posts'))
			                 ->whereIn($db->quoteName($isATS5OrLater ? 'ticket_id' : 'ats_ticket_id'), $ticketIDs, ParameterType::INTEGER);

			$postIDs             = $db->setQuery($postsQuery)->loadColumn(0);
			$ret['ats']['posts'] = $postIDs;
		}

		if (!empty($postIDs))
		{
			// Query for the attachment IDs and the names of the files they are stored in
			$attachmentsQuery          = DbQuery::create($db)
			                                ->select([
				                                $db->quoteName($isATS5OrLater ? 'id' : 'ats_attachment_id', 'id'),
				                                $db->quoteName('mangled_filename'),
			                                ])
			                                ->from($db->quoteName('#__ats_attachments'))
			                                ->whereIn($db->quoteName($isATS5OrLater ? 'post_id' : 'ats_post_id'), $postIDs, ParameterType::INTEGER);
			$attachments               = $db->setQuery($attachmentsQuery)->loadObjectList();
			$ret['ats']['attachments'] = array_column($attachments, 'id');

			// Delete attachments
			$query = DbQuery::create($db)
			            ->delete($db->quoteName('#__ats_attachments'))
			            ->whereIn($db->quoteName($isATS5OrLater ? 'post_id' : 'ats_post_id'), $postIDs, ParameterType::INTEGER);
			$db->setQuery($query)->execute();
			unset($postIDs);

			// Delete the attachment files, like ATS' AttachmentTable does after deleting an attachment record.
			$this->deleteAttachmentFiles(array_column($attachments, 'mangled_filename'));
			unset($attachments);

			// Delete posts
			$query = DbQuery::create($db)
			            ->delete($db->quoteName('#__ats_posts'))
			            ->whereIn($db->quoteName($isATS5OrLater ? 'ticket_id' : 'ats_ticket_id'), $ticketIDs, ParameterType::INTEGER);
			$db->setQuery($query)->execute();
		}

		// Delete tickets
		if (!empty($ticketIDs))
		{
			$query = DbQuery::create($db)
			            ->delete($db->quoteName('#__ats_tickets'))
			            ->whereIn($db->quoteName($isATS5OrLater ? 'id' : 'ats_ticket_id'), $ticketIDs, ParameterType::INTEGER);
			$db->setQuery($query)->execute();

			// ============================== attempts ==============================
			try
			{
				$query = DbQuery::create($db)
				            ->select($db->quoteName('ats_attempt_id'))
				            ->from($db->quoteName('#__ats_attempts'))
				            ->whereIn($db->quoteName('ats_ticket_id'), $ticketIDs, ParameterType::INTEGER);

				$ret['ats']['attempts'] = $db->setQuery($query)->loadColumn();

				$query = DbQuery::create($db)
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
			$query = DbQuery::create($db)
			            ->select($db->quoteName('ats_creditconsumption_id'))
			            ->from($db->quoteName('#__ats_creditconsumptions'))
			            ->where($db->quoteName('user_id') . ' = :userId')
			            ->bind(':userId', $userId, ParameterType::INTEGER);

			$ret['ats']['creditconsumptions'] = $db->setQuery($query)->loadColumn();

			if (!empty($ret['ats']['creditconsumptions']))
			{
				$query = DbQuery::create($db)
				            ->delete($db->quoteName('#__ats_creditconsumptions'))
				            ->where($db->quoteName('user_id') . ' = :userId')
				            ->bind(':userId', $userId, ParameterType::INTEGER);

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
			$query = DbQuery::create($db)
			            ->select($db->quoteName('ats_credittransaction_id'))
			            ->from($db->quoteName('#__ats_credittransactions'))
			            ->where($db->quoteName('user_id') . ' = :userId')
			            ->bind(':userId', $userId, ParameterType::INTEGER);

			$ret['ats']['credittransactions'] = $db->setQuery($query)->loadColumn();

			if (!empty($ret['ats']['credittransactions']))
			{
				$query = DbQuery::create($db)
				            ->delete($db->quoteName('#__ats_credittransactions'))
				            ->where($db->quoteName('user_id') . ' = :userId')
				            ->bind(':userId', $userId, ParameterType::INTEGER);

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
			$query = DbQuery::create($db)
			            ->select($db->quoteName('id'))
			            ->from($db->quoteName('#__ats_users_usertags'))
			            ->where($db->quoteName('user_id') . ' = :userId')
			            ->bind(':userId', $userId, ParameterType::INTEGER);

			$ret['ats']['usertags'] = $db->setQuery($query)->loadColumn();

			if (!empty($ret['ats']['usertags']))
			{
				$query = DbQuery::create($db)
				            ->delete($db->quoteName('#__ats_users_usertags'))
				            ->where($db->quoteName('user_id') . ' = :userId')
				            ->bind(':userId', $userId, ParameterType::INTEGER);

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
	 * - #__ats_tickets, #__ats_posts and #__ats_attachments of the user's tickets
	 * - #__ats_attempts, #__ats_creditconsumptions, #__ats_credittransactions and #__ats_users_usertags (ATS 4 and
	 *   earlier; skipped when the tables do not exist)
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
		$db     = $this->getDatabase();

		// Tickets
		$tickets   = $this->getTickets($userId);
		$ticketIDs = array_map(fn($ticket) => $isATS5OrLater ? $ticket->id : $ticket->ats_ticket_id, $tickets);

		$this->addExportDomain($export, 'ats_tickets', 'Akeeba Ticket System tickets', $tickets);
		unset($tickets);

		// Export #__ats_attempts entries (ATS 4 and earlier)
		if (!empty($ticketIDs))
		{
			try
			{
				$selectQuery = DbQuery::create($db)
				                  ->select('*')
				                  ->from($db->quoteName('#__ats_attempts'))
				                  ->whereIn($db->quoteName('ats_ticket_id'), $ticketIDs, ParameterType::INTEGER);

				$this->addExportDomain(
					$export, 'ats_attempts',
					'Akeeba Ticket System ticket filing attempts (successful), linked to each ticket',
					$db->setQuery($selectQuery)->loadObjectList()
				);
			}
			catch (\Exception $e)
			{
				// The table does not exist in this version of ATS.
			}
		}

		// Posts
		$posts   = $this->getPosts($ticketIDs);
		$postIDs = array_map(fn($post) => $isATS5OrLater ? $post->id : $post->ats_post_id, $posts);

		$this->addExportDomain($export, 'ats_posts', 'Akeeba Ticket System posts, linked to each ticket', $posts);
		unset($posts);

		// Attachments
		$this->addExportDomain(
			$export, 'ats_attachments', 'Akeeba Ticket System attachments, linked to each post',
			$this->getAttachments($postIDs)
		);

		// Export #__ats_creditconsumptions entries (ATS 4 and earlier)
		try
		{
			$selectQuery = DbQuery::create($db)
			                  ->select('*')
			                  ->from($db->quoteName('#__ats_creditconsumptions'))
			                  ->where($db->quoteName('user_id') . ' = :userId')
			                  ->bind(':userId', $userId, ParameterType::INTEGER);

			$this->addExportDomain(
				$export, 'ats_creditconsumptions',
				'Akeeba Ticket System credit consumption events, linked to each ticket',
				$db->setQuery($selectQuery)->loadObjectList()
			);
		}
		catch (\Exception $e)
		{
			// The table does not exist in this version of ATS.
		}

		// Export #__ats_credittransactions entries (ATS 4 and earlier)
		try
		{
			$selectQuery = DbQuery::create($db)
			                  ->select('*')
			                  ->from($db->quoteName('#__ats_credittransactions'))
			                  ->where($db->quoteName('user_id') . ' = :userId')
			                  ->bind(':userId', $userId, ParameterType::INTEGER);

			$this->addExportDomain(
				$export, 'ats_credittransactions', 'Akeeba Ticket System credit transactions (credit purchases)',
				$db->setQuery($selectQuery)->loadObjectList()
			);
		}
		catch (\Exception $e)
		{
			// The table does not exist in this version of ATS.
		}

		// Export #__ats_users_usertags entries (ATS 4 and earlier)
		try
		{
			$selectQuery = DbQuery::create($db)
			                  ->select('*')
			                  ->from($db->quoteName('#__ats_users_usertags'))
			                  ->where($db->quoteName('user_id') . ' = :userId')
			                  ->bind(':userId', $userId, ParameterType::INTEGER);

			$this->addExportDomain(
				$export, 'ats_user_usertags', 'Akeeba Ticket System user tags',
				$db->setQuery($selectQuery)->loadObjectList()
			);
		}
		catch (\Exception $e)
		{
			// The table does not exist in this version of ATS.
		}

		// Only now, after all the optional sections (whose tables may not exist), return the export.
		$this->setEventResult($event, $export);
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

	/**
	 * Add an export domain with the given rows to the export document.
	 *
	 * @param   SimpleXMLElement  $export       The export document (root node)
	 * @param   string            $name         The domain name
	 * @param   string            $description  The domain description
	 * @param   object[]          $items        The rows to export into this domain
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	private function addExportDomain(SimpleXMLElement $export, string $name, string $description, array $items): void
	{
		$domain = $export->addChild('domain');
		$domain->addAttribute('name', $name);
		$domain->addAttribute('description', $description);

		foreach ($items as $item)
		{
			Export::adoptChild($domain, Export::exportItemFromObject($item));
		}
	}

	/**
	 * Delete the files of ATS attachments from disk.
	 *
	 * Mirrors ATS' AttachmentTable::getAbsoluteFilename(): files are stored as ab/cd/abcd… (two-level prefix taken
	 * from the mangled filename itself) or, for attachments uploaded by older ATS versions, directly in the
	 * attachments directory. Both the configured and the default attachments directory are looked into.
	 *
	 * The mangled filenames come from the database. Only well-formed hex hashes are accepted, so that a tampered
	 * value can never point outside the attachments directory.
	 *
	 * @param   array  $mangledFilenames  The mangled filenames of the attachments to delete.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	private function deleteAttachmentFiles(array $mangledFilenames): void
	{
		if (empty($mangledFilenames))
		{
			return;
		}

		$directories = $this->getAttachmentDirectories();

		foreach ($mangledFilenames as $mangledFilename)
		{
			$mangledFilename = (string) $mangledFilename;

			// Same rule as ATS' Attachment::isValidMangledFilename(): MD5, SHA-1 or SHA-256 hex hashes only.
			if (!preg_match('/\A(?:[a-f0-9]{32}|[a-f0-9]{40}|[a-f0-9]{64})\z/i', $mangledFilename))
			{
				continue;
			}

			$relativePath = substr($mangledFilename, 0, 2) . '/' . substr($mangledFilename, 2, 2) . '/' . $mangledFilename;

			foreach ($directories as $directory)
			{
				foreach ([$directory . '/' . $relativePath, $directory . '/' . $mangledFilename] as $filePath)
				{
					if (!@is_file($filePath))
					{
						continue;
					}

					try
					{
						$deleted = File::delete($filePath);
					}
					catch (\Throwable $e)
					{
						$deleted = false;
					}

					if (!$deleted && !@unlink($filePath))
					{
						Log::add(
							sprintf('Could not delete the Akeeba Ticket System attachment file %s', $filePath),
							Log::WARNING, 'com_datacompliance'
						);
					}
				}
			}
		}
	}

	/**
	 * Get the absolute paths of the directories ATS stores attachments in.
	 *
	 * The configured directory is resolved (and validated) by ATS' own Attachment helper, when available. The
	 * default directory is always included: before ATS 5.6.1 a custom attachments folder was ignored.
	 *
	 * @return  string[]
	 * @since   4.1.0
	 */
	private function getAttachmentDirectories(): array
	{
		$directories = [JPATH_ROOT . '/media/com_ats/attachments'];
		$helperClass = 'Akeeba\\Component\\ATS\\Administrator\\Helper\\Attachment';

		try
		{
			if (class_exists($helperClass) && method_exists($helperClass, 'getDirectory'))
			{
				$directories[] = (string) $helperClass::getDirectory();
			}
		}
		catch (\Throwable $e)
		{
			// ATS could not tell us; the default directory is still searched.
		}

		$directories = array_map(fn($dir) => rtrim(str_replace('\\', '/', $dir), '/'), $directories);

		return array_unique(array_filter($directories));
	}

	private function getAttachments(array $postIDs)
	{
		if (empty($postIDs))
		{
			return [];
		}

		$isATS5OrLater = @is_dir(JPATH_ADMINISTRATOR . '/components/com_ats/services');

		$db    = $this->getDatabase();
		$query = DbQuery::create($db)
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
		$query = DbQuery::create($db)
		            ->select('*')
		            ->from('#__ats_posts')
		            ->whereIn($db->quoteName($isATS5OrLater ? 'ticket_id' : 'ats_ticket_id'), $ticketIDs, ParameterType::INTEGER);

		return $db->setQuery($query)->loadObjectList();
	}

	private function getTickets(int $user_id)
	{
		$db    = $this->getDatabase();
		$query = DbQuery::create($db)
		            ->select('*')
		            ->from('#__ats_tickets')
		            ->where($db->quoteName('created_by') . ' = :userId')
		            ->bind(':userId', $user_id, ParameterType::INTEGER);

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