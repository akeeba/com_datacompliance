<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Controller;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Mixin\ControllerEventsTrait;
use Akeeba\Component\DataCompliance\Administrator\Mixin\ControllerReusableModelsTrait;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;

class LifecycleController extends AdminController
{
	use ControllerEventsTrait;
	use ControllerReusableModelsTrait;

	protected $text_prefix = 'COM_DATACOMPLIANCE_LIFECYCLE';

	public function __construct($config = [], MVCFactoryInterface $factory = null, ?CMSApplication $app = null, ?Input $input = null)
	{
		parent::__construct($config, $factory, $app, $input);

		// This is a strictly browse-only view. We must not make changes to the audit trail!
		$this->unregisterTask('unpublish');
		$this->unregisterTask('archive');
		$this->unregisterTask('trash');
		$this->unregisterTask('report');
		$this->unregisterTask('orderup');
		$this->unregisterTask('orderdown');
		$this->unregisterTask('delete');
		$this->unregisterTask('publish');
		$this->unregisterTask('reorder');
		$this->unregisterTask('saveorder');
		$this->unregisterTask('checkin');
		$this->unregisterTask('saveOrderAjax');
		$this->unregisterTask('runTransition');
	}

	public function display($cachable = false, $urlparams = [])
	{
		$view      = $this->getView();
		$wipeModel = $this->getModel('Wipe');
		$view->setModel($wipeModel, false);

		return parent::display($cachable, $urlparams);
	}


	public function getModel($name = 'Lifecycle', $prefix = 'Administrator', $config = ['ignore_request' => true])
	{
		return parent::getModel($name, $prefix, $config);
	}
}