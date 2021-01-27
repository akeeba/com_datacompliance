<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Model;

defined('_JEXEC') or die;

use Akeeba\DataCompliance\Admin\Helper\Export as ExportHelper;
use DOMDocument;
use FOF40\Encrypt\Randval;
use FOF40\Model\Model;
use Joomla\CMS\Table\Table;
use PrivacyExportDomain;
use RuntimeException;
use SimpleXMLElement;

/**
 * A model to export user information (data portability)
 */
class Export extends Model
{
	/**
	 * Exports the user information as a SimpleXMLElement object
	 *
	 * @param   int  $userId  The user ID to export
	 *
	 * @return  SimpleXMLElement
	 *
	 * @throws  RuntimeException  If an error occurred during export
	 */
	public function exportSimpleXML($userId): SimpleXMLElement
	{
		// Create an audit trail entry for this export
		/** @var Exporttrails $trail */
		$trail = $this->container->factory->model('Exporttrails')->tmpInstance();
		$trail->create([
			'user_id' => $userId,
		]);

		// Integrate results from DataCompliance plugins
		$platform = $this->container->platform;
		$platform->importPlugin('datacompliance');
		$results = $platform->runPlugins('onDataComplianceExportUser', [$userId]);

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

				if (!($domain instanceof PrivacyExportDomain))
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
	 * @throws  RuntimeException  If an error occurred during export
	 */
	public function exportXML($userId): string
	{
		$simpleXml = $this->exportSimpleXML($userId);

		return $simpleXml->asXML();
	}

	/**
	 * Export a user profile as pretty formatted XML text
	 *
	 * @param   int  $userId  The user ID to export
	 *
	 * @return  string  The formatted XML document text
	 *
	 * @throws  RuntimeException  If an error occurred during export
	 */
	public function exportFormattedXML($userId): string
	{
		$xml = $this->exportXML($userId);

		$dom = new DOMDocument();
		$dom->loadXML($xml);
		$dom->formatOutput = true;

		return $dom->saveXML();
	}

	/**
	 * Get the results from the Joomla privacy plugins
	 *
	 * @param   int  $userId  The ID of the user being exported
	 *
	 * @return  array
	 */
	protected function getJoomlaPrivacyResults(int $userId): array
	{
		// This feature is available since Joomla! 3.9.0
		if (version_compare(JVERSION, '3.9.0', 'lt'))
		{
			return [];
		}

		// We'll need to go through FOF's platform
		$platform = $this->container->platform;

		// Get a user record
		$user = $platform->getUser($userId);

		// If the user does not exist fail early
		if ($user->id != $userId)
		{
			return [];
		}

		// Try to load the PrivacyTableRequest class
		$tableFile = JPATH_ADMINISTRATOR . '/components/com_privacy/tables/request.php';

		if (!@file_exists($tableFile) && !class_exists('PrivacyTableRequest'))
		{
			return [];
		}
		else
		{
			@include_once $tableFile;
		}

		if (!class_exists('PrivacyTableRequest'))
		{
			return [];
		}

		// Create a (fake) request table object for Joomla's privacy plugins
		/** @var \PrivacyTableRequest $request */
		$request                           = Table::getInstance('Request', 'PrivacyTable');
		$randVal                           = new Randval();
		$rightNow                          = $platform->getDate()->toSql();
		$request->email                    = $user->email;
		$request->requested_at             = $rightNow;
		$request->status                   = 1;
		$request->request_type             = 'export';
		$request->confirm_token            = $randVal->getRandomPassword(32);
		$request->confirm_token_created_at = $rightNow;

		// Import the plugins, run them and return the results
		$platform->importPlugin('privacy');
		$results = $platform->runPlugins('onPrivacyExportRequest', [$request, $user]);

		return $results;
	}
}