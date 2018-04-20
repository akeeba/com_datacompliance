<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Controller;

defined('_JEXEC') or die;

use Akeeba\DataCompliance\Admin\Controller\Mixin\PredefinedTaskList;
use Akeeba\DataCompliance\Admin\Helper\Export;
use FOF30\Container\Container;
use FOF30\Controller\Controller;
use JFactory;
use JText;
use JUri;

class Test extends Controller
{
	use PredefinedTaskList;

	public function __construct(Container $container, array $config)
	{
		parent::__construct($container, $config);

		$this->predefinedTaskList = [
			'test',
		];
	}

	public function test()
	{
		$userID = $this->input->getInt('id');

		$platform = $this->container->platform;
		$platform->importPlugin('datacompliance');
		$results = $platform->runPlugins('onDataComplianceExportUser', [$userID]);

		$export = new \SimpleXMLElement("<root />");

		foreach ($results as $result)
		{
			if (!is_object($result))
			{
				continue;
			}

			if (!($result instanceof \SimpleXMLElement))
			{
				continue;
			}

			$export = Export::merge($export, $result);
		}

		$dom = new \DOMDocument();
		$dom->loadXML($export->asXML());
		$dom->formatOutput = true;
		$xml = $dom->saveXML();

		$xml = htmlentities($xml);
		echo "<pre>$xml</pre>";

		return;
	}
}
