<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\DataCompliance\LoginGuard\Extension;

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
 * Data Compliance plugin for Akeeba LoginGuard User Data
 *
 * @since  1.0.0
 */
class LoginGuard extends CMSPlugin implements SubscriberInterface
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
	 * - Delete the user's LoginGuard TFA method entries
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
			'loginguard' => [
				'tfa' => [],
			],
		];


		Log::add("Deleting user #$userId, type ‘{$type}’, LoginGuard data", Log::INFO, 'com_datacompliance');

		$db = $this->db;
		$db->setMonitor(null);

		$selectQuery = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__loginguard_tfa'))
			->where($db->quoteName('user_id') . ' = :user_id')
			->bind(':user_id', $userId, ParameterType::INTEGER);

		$deleteQuery = $db->getQuery(true)
			->delete($db->quoteName('#__loginguard_tfa'))
			->where($db->quoteName('user_id') . ' = :user_id')
			->bind(':user_id', $userId, ParameterType::INTEGER);

		try
		{
			$ids                      = $db->setQuery($selectQuery)->loadColumn(0);
			$ids                      = empty($ids) ? [] : implode(',', $ids);
			$ret['loginguard']['tfa'] = $ids;

			$db->setQuery($deleteQuery)->execute();
		}
		catch (Exception $e)
		{
			// No problem if deleting fails.
		}

		$this->setEventResult($event, $ret);
	}

	/**
	 * Used for exporting the user information in XML format. The returned data is a SimpleXMLElement document with a
	 * data dump following the structure root > domain > item[...] > column[...].
	 *
	 * This plugin exports the following tables / models:
	 * - #__loginguard_tfa
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

		$db = $this->db;

		$export = new SimpleXMLElement("<root></root>");

		// #__loginguard_tfa
		$domainTfa = $export->addChild('domain');
		$domainTfa->addAttribute('name', 'loginguard_tfa');
		$domainTfa->addAttribute('description', 'Akeeba LoginGuard TFA records');

		$query   = $db->getQuery(true)
			->select('*')
			->from('#__loginguard_tfa')
			->where($db->quoteName('user_id') . ' = :user_id')
			->bind(':user_id', $userId, ParameterType::INTEGER);
		$records = $db->setQuery($query)->loadObjectList();

		foreach ($records as $record)
		{
			Export::adoptChild($domainTfa, Export::exportItemFromObject($record));
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
			Text::_('PLG_DATACOMPLIANCE_LOGINGUARD_ACTIONS_1'),
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