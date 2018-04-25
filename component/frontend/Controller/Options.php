<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Site\Controller;

defined('_JEXEC') or die;

use FOF30\Container\Container;
use FOF30\Controller\Controller;
use FOF30\Controller\Mixin\PredefinedTaskList;

class Options extends Controller
{
	use PredefinedTaskList;

	/**
	 * Options constructor. Sets up the predefined task list.
	 *
	 * @param   Container  $container
	 * @param   array      $config
	 */
	public function __construct(Container $container, array $config = array())
	{
		parent::__construct($container, $config);

		$this->predefinedTaskList = ['options', 'consent', 'export', 'wipeconfirm', 'wipe'];
	}

	/**
	 * Default task, shows a page for the user to make their data protection options.
	 *
	 * @param   string  $tpl
	 */
	public function options($tpl = null)
	{
		$this->display(false, false, $tpl);
	}

	/**
	 * Apply the personal data consent preferences
	 *
	 * @param   string  $tpl
	 *
	 * @throws  \Exception
	 */
	public function consent($tpl = null)
	{
		$this->csrfProtection();

		// TODO
	}

	/**
	 * Export the personal data profile
	 *
	 * @param   string  $tpl
	 *
	 * @throws  \Exception
	 */
	public function export($tpl = null)
	{
		$this->csrfProtection();

		// TODO
	}

	/**
	 * Ask the user for confirmation before wiping their profile
	 *
	 * @param   string  $tpl
	 *
	 * @throws  \Exception
	 */
	public function wipeconfirm($tpl = null)
	{
		$this->csrfProtection();

		// TODO
	}

	/**
	 * Wipe the user's profile
	 *
	 * @param   string  $tpl
	 *
	 * @throws  \Exception
	 */
	public function wipe($tpl = null)
	{
		$this->csrfProtection();

		// TODO
	}

	protected function onBeforeOptions($task)
	{
		// Make sure there is a logged in user
		if ($this->container->platform->getUser()->guest)
		{
			throw new \RuntimeException('JERROR_ALERTNOAUTHOR');
		}
	}

}