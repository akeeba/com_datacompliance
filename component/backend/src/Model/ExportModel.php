<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Model;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Helper\Export as ExportHelper;
use Akeeba\Component\DataCompliance\Administrator\Table\ExporttrailsTable;
use DOMDocument;
use Exception;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\User\UserHelper;
use Joomla\Component\Privacy\Administrator\Export\Domain as ExportDomain;
use Joomla\Component\Privacy\Administrator\Table\RequestTable;
use Joomla\Database\DatabaseDriver;
use RuntimeException;
use SimpleXMLElement;

/**
 * A model to export user information (data portability)
 *
 * @since        1.0.0
 * @noinspection PhpUnused
 */
#[\AllowDynamicProperties]
class ExportModel extends BaseDatabaseModel
{
	/**
	 * Export a user profile as pretty formatted XML text
	 *
	 * @param   int  $userId  The user ID to export
	 *
	 * @return  string  The formatted XML document text
	 *
	 * @throws  RuntimeException|Exception  If an error occurred during export
	 * @since        1.0.0
	 * @noinspection PhpUnused
	 */
	public function exportFormattedXML(int $userId): string
	{
		$xml = $this->exportXML($userId);

		$dom = new DOMDocument();
		$dom->loadXML($xml);
		$dom->formatOutput = true;

		return $dom->saveXML();
	}

	/**
	 * Exports the user information as a SimpleXMLElement object
	 *
	 * @param   int  $userId  The user ID to export
	 *
	 * @return  SimpleXMLElement
	 *
	 * @throws  RuntimeException|Exception  If an error occurred during export
	 * @since   1.0.0
	 */
	public function exportSimpleXML(int $userId): SimpleXMLElement
	{
		// Create an audit trail entry for this export
		/** @var ExporttrailsTable $trail */
		$trail = $this->getMVCFactory()->createTable('Exporttrails', 'Administrator');
		$trail->reset();
		$trail->save([
			'user_id' => $userId,
		]);

		// Integrate results from DataCompliance plugins
		PluginHelper::importPlugin('datacompliance');
		$results = $this->runPlugins('onDataComplianceExportUser', [$userId]);

		$export = new SimpleXMLElement("<root />");

		foreach ($results as $result)
		{
			if (!is_object($result))
			{
				continue;
			}

			if (!($result instanceof SimpleXMLElement))
			{
				continue;
			}

			$export = ExportHelper::merge($export, $result);
		}

		// Integrate results from Joomla privacy plugins
		$pluginResults = $this->getJoomlaPrivacyResults($userId);

		/**
		 * Note: the abstract PrivacyPlugin class will register autoloaders for the PrivacyExportDomain,
		 *       PrivacyExportField and PrivacyExportItem classes. If we have any results coming from the plugins then
		 *       these classes, which need to be loaded for the rest of the code to work, will be autoloaded.
		 */
		foreach ($pluginResults as $result)
		{
			if (!is_array($result) || empty($result))
			{
				continue;
			}

			foreach ($result as $domain)
			{
				if (!is_object($domain))
				{
					continue;
				}

				if (!($domain instanceof ExportDomain))
				{
					continue;
				}

				$export = ExportHelper::merge($export, ExportHelper::mapJoomlaPrivacyExportDomain($domain));
			}

		}

		return $export;
	}

	/**
	 * Export a user profile as unformatted XML text
	 *
	 * @param   int  $userId  The user ID to export
	 *
	 * @return  string  The unformatted XML document text
	 *
	 * @throws  RuntimeException|Exception  If an error occurred during export
	 * @since   1.0.0
	 */
	public function exportXML(int $userId): string
	{
		$simpleXml = $this->exportSimpleXML($userId);

		return $simpleXml->asXML();
	}

	/**
	 * Get the results from the Joomla privacy plugins
	 *
	 * @param   int  $userId  The ID of the user being exported
	 *
	 * @return  array
	 * @throws  Exception
	 * @since   1.0.0
	 */
	protected function getJoomlaPrivacyResults(int $userId): array
	{
		// This feature is available since Joomla! 3.9.0
		if (version_compare(JVERSION, '3.9.0', 'lt'))
		{
			return [];
		}

		// Get a user record
		$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);

		// If the user does not exist fail early
		if ($user->id != $userId)
		{
			return [];
		}

		// Create a (fake) request table object for Joomla's privacy plugins
		/** @var DatabaseDriver $db */
		$db      = $this->getDatabase();
		$request = new RequestTable($db);

		$rightNow                          = (new Date())->toSql();
		$request->email                    = $user->email;
		$request->requested_at             = $rightNow;
		$request->status                   = 1;
		$request->request_type             = 'export';
		$request->confirm_token            = UserHelper::genRandomPassword(32);
		$request->confirm_token_created_at = $rightNow;

		// Import the plugins, run them and return the results
		PluginHelper::importPlugin('privacy');

		return $this->runPlugins('onPrivacyExportRequest', [$request, $user]);
	}

	/**
	 * Execute plugins (system-level triggers) and fetch back an array with their return values.
	 *
	 * @param   string  $event  The event (trigger) name, e.g. onBeforeScratchMyEar
	 * @param   array   $data   A hash array of data sent to the plugins as part of the trigger
	 *
	 * @return  array  A simple array containing the results of the plugins triggered
	 *
	 * @throws  Exception
	 * @since   1.0.0
	 */
	private function runPlugins(string $event, array $data = []): array
	{
		/** @noinspection PhpDeprecationInspection */
		return Factory::getApplication()->triggerEvent($event, $data);
	}

}