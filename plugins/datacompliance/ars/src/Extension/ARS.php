<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\DataCompliance\ARS\Extension;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Helper\Export;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use SimpleXMLElement;

/**
 * Data Compliance plugin for Akeeba Release System User Data
 *
 * @since  1.0.0
 */
class ARS extends CMSPlugin implements SubscriberInterface
{
	use MVCFactoryAwareTrait;

	/**
	 * The CMS application we are running under.
	 *
	 * @var   CMSApplication
	 * @since 3.0.0
	 */
	protected $app;

	/**
	 * The database driver object
	 *
	 * @var   DatabaseDriver
	 * @since 3.0.0
	 */
	protected $db;

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
		/**
		 * @var int    $userId The user ID we are asked to delete
		 * @var string $type   The export type (user, admin, lifecycle)
		 */
		[$userId, $type] = $event->getArguments();

		$ret = [
			'ars' => [
				'log'  => [],
				'dlid' => [],
			],
		];

		Log::add("Deleting user #$userId, type ‘{$type}’, Akeeba Release System data", Log::INFO, 'com_datacompliance');

		$db = $this->db;
		$db->setMonitor(null);

		// ======================================== Log entries ========================================

		$selectQuery = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__ars_log'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));
		$deleteQuery = $db->getQuery(true)
			->delete($db->quoteName('#__ars_log'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));

		try
		{
			$ids = $db->setQuery($selectQuery)->loadColumn(0);

			Log::add(sprintf("Found %u ARS log entries", count($ids)), Log::DEBUG, 'com_datacompliance');

			$ret['ars']['log'] = $ids;

			unset($ids);

			$db->setQuery($deleteQuery)->execute();
		}
		catch (Exception $e)
		{
			Log::add("Could not delete ARS log data for user #$userId: {$e->getMessage()}", Log::ERROR, 'com_datacompliance');
			Log::add("Debug backtrace: {$e->getTraceAsString()}", Log::DEBUG, 'com_datacompliance');

			// No problem if deleting fails.
		}

		unset($selectQuery);
		unset($deleteQuery);

		// ======================================== Download IDs ========================================

		$selectQuery = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__ars_dlidlabels'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));

		$deleteQuery = $db->getQuery(true)
			->delete($db->quoteName('#__ars_dlidlabels'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));

		try
		{
			$ids = $db->setQuery($selectQuery)->loadColumn(0);

			Log::add(sprintf("Found %u ARS Download IDs", count($ids)), Log::DEBUG, 'com_datacompliance');

			$ret['ars']['dlid'] = $ids;

			unset($ids);

			$db->setQuery($deleteQuery)->execute();
		}
		catch (Exception $e)
		{
			Log::add("Could not delete ARS Download ID data for user #$userId: {$e->getMessage()}", Log::ERROR, 'com_datacompliance');
			Log::add("Debug backtrace: {$e->getTraceAsString()}", Log::DEBUG, 'com_datacompliance');

			// No problem if deleting fails.
		}

		unset($selectQuery);
		unset($deleteQuery);
		unset($db);

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
		/** @var int $userId */
		[$userId] = $event->getArguments();

		$export = new SimpleXMLElement("<root></root>");
		$db     = $this->db;

		// #__ars_log
		$domain = $export->addChild('domain');
		$domain->addAttribute('name', 'ars_log');
		$domain->addAttribute('description', 'Akeeba Release System download log');

		$selectQuery = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__ars_log'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));

		foreach ($db->setQuery($selectQuery)->getIterator() as $record)
		{
			Export::adoptChild($domain, Export::exportItemFromObject($record));

			unset($record);
		}

		// #__ars_dlidlables
		$domain = $export->addChild('domain');
		$domain->addAttribute('name', 'ars_dlidlables');
		$domain->addAttribute('description', 'Akeeba Release System download IDs (main and add-on)');

		$selectQuery = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__ars_dlidlabels'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));

		foreach ($db->setQuery($selectQuery)->getIterator() as $record)
		{
			Export::adoptChild($domain, Export::exportItemFromObject($record));

			unset($record);
		}

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
		[$userId, $type] = $event->getArguments();

		$this->setEventResult($event, [
			Text::_('PLG_DATACOMPLIANCE_ARS_ACTIONS_1'),
		]);
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