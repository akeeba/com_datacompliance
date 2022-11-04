<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\DataCompliance\S3\Extension;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Table\WipetrailsTable;
use Akeeba\Engine\Postproc\Connector\S3v4\Acl;
use Akeeba\Engine\Postproc\Connector\S3v4\Configuration;
use Akeeba\Engine\Postproc\Connector\S3v4\Connector;
use Akeeba\Engine\Postproc\Connector\S3v4\Input;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * Data Compliance plugin for uploading wipe audit trails to Amazon S3
 *
 * @since  1.0.0
 */
class S3 extends CMSPlugin implements SubscriberInterface
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
			'onDataComplianceSaveWipeAuditRecord' => 'onDataComplianceSaveWipeAuditRecord',
		];
	}

	/**
	 * Uploads a copy of the audit trail record to Amazon S3 in JSON format.
	 *
	 * The uploaded file content consists of the JSON representation of the record, minus the audit record ID. The name
	 * of the file is a combination of the deleted user's ID and the sha1 sum of the file contents.
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 */
	public function onDataComplianceSaveWipeAuditRecord(Event $event)
	{
		/** @var WipetrailsTable $auditRecord */
		[$auditRecord] = $event->getArguments();

		if ($this->getApplication()->getSession()->get('com_datacompliance.__audit_replay', 0))
		{
			Log::add("Will NOT upload an audit trail to S3", Log::DEBUG, 'com_datacompliance');

			$this->setEventResult($event, false);

			return;
		}

		Log::add("Preparing to upload audit trail to S3", Log::DEBUG, 'com_datacompliance');

		$data = $auditRecord->getProperties();

		if (isset($data['datacompliance_wipetrail_id']))
		{
			unset($data['datacompliance_wipetrail_id']);
		}

		$json     = json_encode($data);
		$fileName = $auditRecord->user_id . '_' . sha1($json) . '.json';

		// Get an Amazon S3 uploader
		try
		{
			$s3 = $this->getS3Connector();
		}
		catch (Exception $e)
		{
			// Ugh, the user has not provided adequate connection information. Abort.
			Log::add("Could not create a connector for S3: {$e->getMessage()}", Log::ERROR, 'com_datacompliance');
			Log::add("Stack trace: {$e->getTraceAsString()}", Log::ERROR, 'com_datacompliance');

			$this->setEventResult($event, false);

			return;
		}

		// Create the upload object
		$input = Input::createFromData($json);

		// Storage class
		$headers = [
			'X-Amz-Storage-Class' => $this->params->get('class', 'STANDARD'),
		];

		// Figure out where to store the files
		$bucket = $this->params->get('bucket', '');
		$path   = $this->params->get('path', '');

		// We actually need a bucket
		if (empty($bucket))
		{
			$this->setEventResult($event, false);

			return;
		}

		// Get the path to the file
		$path = trim($path, '/');
		$path .= '/' . $fileName;
		$path = ltrim($path, '/');

		// And upload it to S3!
		try
		{
			$s3->putObject($input, $bucket, $path, Acl::ACL_PRIVATE, $headers);
		}
		catch (\Exception $e)
		{
			// Oops. It failed. But I cannot die.
			Log::add("Could not upload audit log to S3: {$e->getMessage()}", Log::ERROR, 'com_datacompliance');
			Log::add("Stack trace: {$e->getTraceAsString()}", Log::ERROR, 'com_datacompliance');

			$this->setEventResult($event, false);
		}

		$this->setEventResult($event, true);
	}

	/**
	 * Get an S3 connector object
	 *
	 * @return  Connector
	 * @since   1.0.0
	 */
	private function getS3Connector(): Connector
	{
		if (!defined('AKEEBAENGINE'))
		{
			define('AKEEBAENGINE', 1);
		}

		if (!class_exists('Akeeba\\Engine\\Postproc\\Connector\\S3v4\\Connector'))
		{
			include_once JPATH_ADMINISTRATOR . '/components/com_datacompliance/vendor/autoload.php';
		}

		if (!class_exists('Akeeba\\Engine\\Postproc\\Connector\\S3v4\\Connector'))
		{
			throw new \RuntimeException("Could not get the Composer autoloader.");
		}

		$s3Configuration = new Configuration(
			$this->params->get('access', ''),
			$this->params->get('secret', ''),
			$this->params->get('method', ''),
			$this->params->get('region', '')
		);

		$useSSL = $this->params->get('ssl', '1');
		$s3Configuration->setSSL($useSSL);

		// If SSL is not enabled you must not provide the CA root file.
		if ($useSSL && !defined('AKEEBA_CACERT_PEM'))
		{
			$caCertPath = class_exists('\\Composer\\CaBundle\\CaBundle')
				? \Composer\CaBundle\CaBundle::getBundledCaBundlePath()
				: JPATH_LIBRARIES . '/src/Http/Transport/cacert.pem';

			define('AKEEBA_CACERT_PEM', $caCertPath);
		}

		// Create the S3 client instance
		return new Connector($s3Configuration);
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