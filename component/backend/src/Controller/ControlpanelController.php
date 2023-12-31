<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Controller;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Mixin\ControllerCacheTrait;
use Akeeba\Component\DataCompliance\Administrator\Mixin\ControllerEventsTrait;
use Akeeba\Component\DataCompliance\Administrator\Mixin\ControllerRegisterTasksTrait;
use Akeeba\Component\DataCompliance\Administrator\Mixin\ControllerReusableModelsTrait;
use DateInterval;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;

/**
 * Controller for the Control Panel page
 *
 * @since  1.0.0
 * @noinspection PhpUnused
 */
class ControlpanelController extends BaseController
{
	use ControllerEventsTrait;
	use ControllerRegisterTasksTrait;
	use ControllerCacheTrait;
	use ControllerReusableModelsTrait;

	/** @inheritdoc  */
	public function __construct($config = [], MVCFactoryInterface $factory = null, ?CMSApplication $app = null, ?Input $input = null)
	{
		parent::__construct($config, $factory, $app, $input);

		$this->registerControllerTasks();
	}


	/**
	 * Get user statistics (active / inactive users), used for displaying graphs
	 *
	 * @since 1.0.0
	 * @noinspection PhpPossiblePolymorphicInvocationInspection
	 */
	public function userstats(): void
	{
		$stats = $this->getCache()->get(function () {
			return $this->getModel()->getUserStats();
		}, [], 'userstats');

		echo json_encode($stats);

		$this->app->close();
	}

	/**
	 * Return information about user accounts deleted, used for displaying graphs
	 *
	 * @throws Exception
	 * @since  1.0.0
	 *
	 * @noinspection PhpPossiblePolymorphicInvocationInspection
	 * @noinspection PhpUnused
	 */
	public function wipedstats(): void
	{
		$to = clone Factory::getDate();
		$to->setTime(0, 0);
		$from = clone Factory::getDate();
		$from->sub(new DateInterval('P1M'));
		$from->setTime(0, 0);
		$to->setTime(23, 59, 59);

		$stats = $this->getCache()->get(function ($from, $to) {
			$statsModel = $this->getModel('Stats', 'Administrator', ['ignore_request' => true]);

			return $statsModel->wipeStats($from, $to);

		}, [$from, $to], 'wipedstats');

		echo json_encode($stats);

		$this->app->close();
	}

}