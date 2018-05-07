<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Controller;

defined('_JEXEC') or die;

use FOF30\Container\Container;
use FOF30\Controller\DataController;
use FOF30\Controller\Mixin\PredefinedTaskList;

/**
 * Abstract controller which only allows Browse views
 *
 * @package Akeeba\DataCompliance\Admin\Controller
 */
class AbstractBrowseOnly extends DataController
{
	use PredefinedTaskList;

	public function __construct(Container $container, array $config = array())
	{
		parent::__construct($container, $config);

		$this->predefinedTaskList = ['browse', 'read'];

		// Map all ACLs to false to prevent modifying the audit trail
		$this->taskPrivileges = [
			// Special privileges
			'*editown' => 'false',
			// Standard tasks
			'add' => 'false',
			'apply' => 'false',
			'archive' => 'false',
			'cancel' => 'false',
			'copy' => 'false',
			'edit' => 'false',
			'loadhistory' => 'false',
			'orderup' => 'false',
			'orderdown' => 'false',
			'publish' => 'false',
			'remove' => 'false',
			'forceRemove' => 'false',
			'save' => 'false',
			'savenew' => 'false',
			'saveorder' => 'false',
			'trash' => 'false',
			'unpublish' => 'false',
		];
	}

	protected function onBeforeExecute(&$task)
	{
		// Require the com_datawipe.view_trail privilege to display this view
		if (!$this->container->platform->getUser()->authorise('view_trail', 'com_datacompliance'))
		{
			throw new \RuntimeException(\JText::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}
	}

}