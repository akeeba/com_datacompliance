<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Controller;

defined('_JEXEC') or die;

use Akeeba\DataCompliance\Admin\Model\Stats;
use DateInterval;
use FOF40\Container\Container;
use FOF40\Controller\Controller;
use FOF40\Controller\Mixin\PredefinedTaskList;
use FOF40\Utils\ViewManifestMigration;
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
			'wipedstats',
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

		// Migrate view XML manifest
		ViewManifestMigration::migrateJoomla4MenuXMLFiles($this->container);
		ViewManifestMigration::removeJoomla3LegacyViews($this->container);
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

	public function wipedstats()
	{
		$to = $this->container->platform->getDate();
		$to->setTime(0, 0, 0);
		$from = $this->container->platform->getDate();
		$from->sub(new DateInterval('P1M'));
		$from->setTime(0, 0, 0);
		$to->setTime(23, 59, 59);

		/** @var Stats $statsModel */
		$statsModel          = $this->container->factory->model('Stats')->tmpInstance();

		echo json_encode($statsModel->wipeStats($from, $to));

		$this->container->platform->closeApplication();
	}
}
