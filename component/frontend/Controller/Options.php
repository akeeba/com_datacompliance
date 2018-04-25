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

		// Make sure there's no buffered data
		@ob_end_clean();

		// Get the export data
		$export  = $this->container->factory->model('Export')->tmpInstance();
		$user_id = $this->container->platform->getUser()->id;
		$result  = $export->exportFormattedXML($user_id);

		// Disable caching
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: public", false);

		// Send MIME headers
		header("Content-Description: File Transfer");
		header('Content-Type: application/xml');
		header("Accept-Ranges: bytes");
		header('Content-Disposition: attachment; filename=export.xml');
		header('Content-Transfer-Encoding: binary');
		header('Connection: close');
		header('Content-Length: ' . (int)strlen($result));

		// Send the data
		echo $result;

		// Make sure everything's spat to the browser and off we go.
		@ob_flush();
		flush();

		$this->container->platform->closeApplication();
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