<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\DataCompliance\Admin\Model\Wipetrails;
use Akeeba\Engine\Postproc\Connector\S3v4\Acl;
use Akeeba\Engine\Postproc\Connector\S3v4\Configuration;
use Akeeba\Engine\Postproc\Connector\S3v4\Connector;
use Akeeba\Engine\Postproc\Connector\S3v4\Input;
use Joomla\CMS\Log\Log;

defined('_JEXEC') or die;

/**
 * Data Compliance plugin for exporting to Amazon S3
 */
class plgDatacomplianceS3 extends Joomla\CMS\Plugin\CMSPlugin
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
	 * Uploads a copy of the audit trail record to Amazon S3 in JSON format.
	 *
	 * The uploaded file content consists of the JSON representation of the record, minus the audit record ID. The name
	 * of the file is a combination of the deleted user's ID and the sha1 sum of the file contents.
	 *
	 * @param   Wipetrails  $model
	 *
	 * @return  void
	 */
	public function onComDatacomplianceModelWipetrailsAfterSave($model)
	{
		Log::add("Preparing to upload audit trail to S3", Log::DEBUG, 'com_datacompliance');

		$data = $model->getData();

		if (isset($data['datacompliance_wipetrail_id']))
		{
			unset($data['datacompliance_wipetrail_id']);
		}

		$json     = json_encode($data);
		$fileName = $model->user_id . '_' . sha1($json) . '.json';

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

			return;
		}

		// Create the upload object
		$input = Input::createFromData($json);

		// Storage class
		$headers = array(
			'X-Amz-Storage-Class' => $this->params->get('class', 'STANDARD'),
		);

		// Figure out where to store the files
		$bucket = $this->params->get('bucket', '');
		$path   = $this->params->get('path', '');

		// We actually need a bucket
		if (empty($bucket))
		{
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
		}
	}

	/**
	 * Get an S3 connector object
	 *
	 * @return  Connector
	 */
	private function getS3Connector()
	{
		if (!class_exists('Akeeba\\Engine\\Postproc\\Connector\\S3v4\\Connector'))
		{
			\FOF30\Autoloader\Autoloader::getInstance()->addMap(
				'Akeeba\\Engine\\Postproc\\Connector\\S3v4\\', array(
					$this->container->backEndPath . '/vendor/akeeba/s3/src'
				)
			);
		}

		if (!defined('AKEEBAENGINE'))
		{
			define('AKEEBAENGINE', 1);
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
			define('AKEEBA_CACERT_PEM', JPATH_LIBRARIES . '/fof30/Download/Adapter/cacert.pem');
		}

		// Create the S3 client instance
		return new Connector($s3Configuration);
	}
}