<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Model;

defined('_JEXEC') or die;

use Akeeba\DataCompliance\Admin\Helper\Export as ExportHelper;
use FOF30\Model\Model;
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
	 * @throws  \RuntimeException  If an error occurred during export
	 */
	public function exportSimpleXML($userId): SimpleXMLElement
	{
		// Create an audit trail entry for this export
		/** @var Exporttrails $trail */
		$trail = $this->container->factory->model('Exporttrails')->tmpInstance();
		$trail->create([
			'user_id' => $userId
		]);

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

		return $export;
	}

	/**
	 * Export a user profile as unformatted XML text
	 *
	 * @param   int  $userId  The user ID to export
	 *
	 * @return  string  The unformatted XML document text
	 *
	 * @throws  \RuntimeException  If an error occurred during export
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
	 * @throws  \RuntimeException  If an error occurred during export
	 */
	public function exportFormattedXML($userId): string
	{
		$xml = $this->exportXML($userId);

		$dom = new \DOMDocument();
		$dom->loadXML($xml);
		$dom->formatOutput = true;

		return $dom->saveXML();
	}
}