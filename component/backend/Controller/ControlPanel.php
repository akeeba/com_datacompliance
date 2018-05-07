<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Controller;

defined('_JEXEC') or die;

use FOF30\Container\Container;
use FOF30\Controller\Controller;
use FOF30\Controller\Mixin\PredefinedTaskList;
use Joomla\CMS\Cache\Controller\CallbackController;

class ControlPanel extends Controller
{
	use PredefinedTaskList;

	public function __construct(Container $container, array $config)
	{
		parent::__construct($container, $config);

		$this->predefinedTaskList = [
			'browse',
			'changelog',
			'userstats',
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

	/**
	 * Get user statistics (active / inactive users) for graphs
	 */
	public function userstats()
	{
		/** @var CallbackController $cache */
		$cache = \JFactory::getCache($this->container->componentName, 'callback');
		$stats = $cache->get(function () {
			/** @var \Akeeba\DataCompliance\Admin\Model\ControlPanel $model */
			$model = $this->getModel();

			return $model->getUserStats();

		}, [], 'userstats');
		
		echo json_encode($stats);

		$this->container->platform->closeApplication();
	}
}
