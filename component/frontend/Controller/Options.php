<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Site\Controller;

defined('_JEXEC') or die;

use Akeeba\DataCompliance\Admin\Model\Wipe;
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

		$this->predefinedTaskList = ['options', 'consent', 'export', 'wipe'];
		$this->csrfProtection     = 1;
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

		/** @var \Akeeba\DataCompliance\Site\Model\Options $model */
		$model = $this->getModel();

		$model->recordPreference($this->input->getBool('enabled', false));

		$url = \JRoute::_('index.php?option=com_datacompliance&view=Options');
		$message = \JText::_('COM_DATACOMPLIANCE_OPTIONS_CONSENT_MSG_RECORDED');
		$this->setRedirect($url, $message);
		$this->redirect();
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
	 * Wipe the user's profile (ask for confirmation first)
	 *
	 * @param   string  $tpl
	 *
	 * @throws  \Exception
	 */
	public function wipe($tpl = null)
	{
		$this->csrfProtection();

		$phrase = $this->input->getString('phrase', null);
		$user   = $this->container->platform->getUser();
		/** @var Wipe $wipeModel */
		$wipeModel = $this->container->factory->model('Wipe')->tmpInstance();

		// Can the user be wiped, at all?
		if (!$wipeModel->checkWipeAbility($user->id))
		{
			$msg         = \JText::sprintf('COM_DATACOMPLIANCE_OPTIONS_WIPE_ERR_CANNOTBEERASED', $wipeModel->getError());
			$redirectUrl = \JRoute::_('index.php?option=com_datacompliance&view=Options');
			$this->setRedirect($redirectUrl, $msg, 'error');
			$this->redirect();
		}

		// If 'phrase' is not set just skip to displaying the confirmation interface
		if (is_null($phrase))
		{
			$this->display($tpl);

			return;
		}

		// Confirm the phrase
		if ($phrase != \JText::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_CONFIRMPHRASE'))
		{
			$token = $this->container->platform->getToken();
			$redirectUrl = \JRoute::_('index.php?option=com_datacompliance&view=Options&task=wipe&' . $token . '=1');
			$this->setRedirect($redirectUrl, \JText::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_ERR_BADPHRASE'), 'error');
			$this->redirect();

			return;
		}

		// Try to delete the user
		$result = $wipeModel->wipe($user->id);

		if (!$result)
		{
			$token = $this->container->platform->getToken();
			$redirectUrl = \JRoute::_('index.php?option=com_datacompliance&view=Options&task=wipe&' . $token . '=1');
			$message     = \JText::sprintf('COM_DATACOMPLIANCE_OPTIONS_WIPE_ERR_DELETEFAILED', $wipeModel->getError());
			$this->setRedirect($redirectUrl, $message, 'error');
			$this->redirect();

			return;
		}

		// Log out the now erased user and redirect them to the home page
		$this->container->platform->logoutUser();

		$redirectUrl = \JRoute::_('index.php');
		$message     = \JText::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_MSG_ERASED');
		$this->setRedirect($redirectUrl, $message);
		$this->redirect();
	}

	protected function onBeforeOptions($task)
	{
		// Make sure there is a logged in user
		if ($this->container->platform->getUser()->guest)
		{
			throw new \RuntimeException(\JText::_('JERROR_ALERTNOAUTHOR'), 403);
		}
	}

}