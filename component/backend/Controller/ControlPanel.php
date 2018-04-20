<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Controller;

defined('_JEXEC') or die;

use Akeeba\DataCompliance\Admin\Controller\Mixin\PredefinedTaskList;
use FOF30\Container\Container;
use FOF30\Controller\Controller;
use JFactory;
use JText;
use JUri;

class ControlPanel extends Controller
{
	use PredefinedTaskList;

	public function __construct(Container $container, array $config)
	{
		parent::__construct($container, $config);

		$this->predefinedTaskList = [
			'browse',
			'changelog',
		];
	}

	public function onBeforeBrowse()
	{
		/** @var \Akeeba\DataCompliance\Admin\Model\ControlPanel $model */
		$model = $this->getModel();

		// Upgrade the database schema if necessary
		$model->checkAndFixDatabase();

		// Update the magic parameters
		$model->updateMagicParameters();
	}

	public function changelog()
	{
		$view = $this->getView();
		$view->setLayout('changelog');

		$this->display(true);
	}
}
