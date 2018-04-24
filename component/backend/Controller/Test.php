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
			'export', 'wipe'
		];
	}

	public function export()
	{
		$start = microtime(true);
		$memStart = memory_get_usage();
		$userID = $this->input->getInt('id');
		/** @var \Akeeba\DataCompliance\Admin\Model\Export $export */
		$export = $this->container->factory->model('Export')->tmpInstance();
		$result = $export->exportFormattedXML($userID);
		$result = htmlentities($result);
		$end = microtime(true);
		$memEnd = memory_get_usage();

		$duration = $end - $start;
		$mem = ($memEnd - $memStart) / 1024 / 1024;
		$peakmem = memory_get_peak_usage() / 1024 / 1024;
		echo "<h1>Export</h1>";
		echo "<p>Time: $duration seconds</p>";
		echo "<p>Memory: $mem MB (end usage: $memEnd)</p>";
		echo "<p>Peak Memory: $peakmem MB</p>";

		echo "<pre>$result</pre>";

		return;
	}

	public function wipe()
	{
		$userID = $this->input->getInt('id');
		/** @var \Akeeba\DataCompliance\Admin\Model\Wipe $wipe */
		$wipe = $this->container->factory->model('Wipe')->tmpInstance();
		$result = $wipe->wipe($userID);

		var_dump($result, $wipe->getError());

		return;
	}
}
