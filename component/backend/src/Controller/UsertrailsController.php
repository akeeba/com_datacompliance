<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Controller;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Mixin\ControllerEventsTrait;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;

class UsertrailsController extends AdminController
{
	use ControllerEventsTrait;

	protected $text_prefix = 'COM_DATACOMPLIANCE_USERTRAILS';

	public function __construct($config = [], ?MVCFactoryInterface $factory = null, ?CMSApplication $app = null, ?Input $input = null)
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
		// Defence in depth: the dispatcher already enforces core.manage, but if a future
		// contributor re-registers a state-changing task on this controller they would
		// bypass every line of defence. Re-check the privilege here too.
		$user = Factory::getApplication()->getIdentity();

		if (!$user->authorise('core.manage', 'com_datacompliance'))
		{
			throw new \Joomla\CMS\Access\Exception\NotAllowed(
				Text::_('JERROR_ALERTNOAUTHOR'),
				403
			);
		}

		return parent::display($cachable, $urlparams);
	}

	public function getModel($name = 'Usertrails', $prefix = 'Administrator', $config = ['ignore_request' => true])
	{
		return parent::getModel($name, $prefix, $config);
	}
}