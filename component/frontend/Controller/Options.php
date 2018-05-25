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
use RuntimeException;

class Options extends Controller
{
	use PredefinedTaskList;

	/**
	 * Options constructor. Sets up the predefined task list.
	 *
	 * @param   Container  $container
	 * @param   array      $config
	 *
	 * @since   1.0.0
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
	 *
	 * @since   1.0.0
	 */
	public function options($tpl = null)
	{
		$this->assertUserAccess();

		$this->display(false, false, $tpl);
	}

	/**
	 * Apply the personal data consent preferences
	 *
	 * @param   string  $tpl
	 *
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	public function consent($tpl = null)
	{
		$this->csrfProtection();

		/** @var \Akeeba\DataCompliance\Site\Model\Options $model */
		$model = $this->getModel();

		$model->recordPreference($this->input->getBool('enabled', false));

		$defaultUrl = \JRoute::_('index.php?option=com_datacompliance&view=Options', false);
		$returnUrl = $this->container->platform->getSessionVar('return_url', $defaultUrl, 'com_datacompliance');

		$message = \JText::_('COM_DATACOMPLIANCE_OPTIONS_CONSENT_MSG_RECORDED');
		$this->setRedirect($returnUrl, $message);
		$this->redirect();
	}

	/**
	 * Export the personal data profile
	 *
	 * @param   string  $tpl
	 *
	 * @throws  \Exception
	 *
	 * @since   1.0.0
	 */
	public function export($tpl = null)
	{
		$this->csrfProtection();
		$this->assertUserAccess();

		$currentUser = $this->container->platform->getUser();
		$userID      = $this->input->getInt('user_id', $currentUser->id);

		// You can only export your own data unless you have the 'com_datawipe.export' privilege
		if (($userID != $currentUser->id) && !$currentUser->authorise('export', 'com_datacompliance'))
		{
			$this->container->platform->raiseError(403, \JText::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'));
		}

		// Make sure there's no buffered data
		@ob_end_clean();

		// Get the export data
		$export = $this->container->factory->model('Export')->tmpInstance();
		$result = $export->exportFormattedXML($userID);

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
		header('Content-Length: ' . (int) strlen($result));

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
	 *
	 * @since   1.0.0
	 */
	public function wipe($tpl = null)
	{
		$this->csrfProtection();
		$this->assertUserAccess();

		$currentUser = $this->container->platform->getUser();
		$userID      = $this->input->getInt('user_id', $currentUser->id);
		$isCurrent   = $userID == $currentUser->id;

		// You can only delete your own data unless you have the 'com_datawipe.wipe' privilege
		if (!$isCurrent && !$currentUser->authorise('wipe', 'com_datacompliance'))
		{
			$this->container->platform->raiseError(403, \JText::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'));
		}

		$phrase = $this->input->getString('phrase', null);
		$user   = $this->container->platform->getUser($userID);
		/** @var Wipe $wipeModel */
		$wipeModel = $this->container->factory->model('Wipe')->tmpInstance();

		// Can the user be wiped, at all?
		if (!$wipeModel->checkWipeAbility($user->id))
		{
			$msg         = \JText::sprintf('COM_DATACOMPLIANCE_OPTIONS_WIPE_ERR_CANNOTBEERASED', $wipeModel->getError());
			$url         = 'index.php?option=com_datacompliance&view=Options';
			$url         .= empty($userID) ? '' : ('&user_id=' . $userID);
			$redirectUrl = \JRoute::_($url, false);
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
			$token       = $this->container->platform->getToken();
			$url         = 'index.php?option=com_datacompliance&view=Options&task=wipe&' . $token . '=1';
			$url         .= empty($userID) ? '' : ('&user_id=' . $userID);
			$redirectUrl = \JRoute::_($url, false);
			$this->setRedirect($redirectUrl, \JText::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_ERR_BADPHRASE'), 'error');
			$this->redirect();

			return;
		}

		// Try to delete the user
		$result = $wipeModel->wipe($user->id, 'user');

		if (!$result)
		{
			$token       = $this->container->platform->getToken();
			$url         = 'index.php?option=com_datacompliance&view=Options&task=wipe&' . $token . '=1';
			$url         .= empty($userID) ? '' : ('&user_id=' . $userID);
			$redirectUrl = \JRoute::_($url, false);
			$message     = \JText::sprintf('COM_DATACOMPLIANCE_OPTIONS_WIPE_ERR_DELETEFAILED', $wipeModel->getError());
			$this->setRedirect($redirectUrl, $message, 'error');
			$this->redirect();

			return;
		}


		// Log out the now erased user
		if ($isCurrent)
		{
			\JFactory::getApplication()->getSession()->close();
			\JFactory::getApplication()->getSession()->restart();
		}

		// Redirect them to the home page
		$message     = \JText::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_MSG_ERASED');
		\JFactory::getApplication()->enqueueMessage($message);
		\JFactory::getApplication()->redirect(\JUri::base());
	}

	/**
	 * Ensures that the user has adequate access to fulfil the request.
	 *
	 * @return   void
	 *
	 * @since    1.0.0
	 *
	 * @throws   RuntimeException  If access is not allowed
	 */
	private function assertUserAccess()
	{
		// Make sure there is a logged in user
		$user = $this->container->platform->getUser();

		if ($user->guest)
		{
			throw new \RuntimeException(\JText::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Get the user_id from the URL
		$user_id = $this->input->getInt('user_id', null);

		// No user ID specified? Good!
		if (is_null($user_id))
		{
			return;
		}

		// The user ID is ourselves? Good!
		if ($user_id == $user->id)
		{
			return;
		}

		// Wait. You are asking to access another user. Do you have permission to do so?
		$canExport = $user->authorise('export', 'com_datacompliance');
		$canWipe   = $user->authorise('wipe', 'com_datacompliance');

		if (!$canExport && !$canExport)
		{
			// Neither privilege is granted. You are trying to do something naughty.
			throw new \RuntimeException(\JText::_('JERROR_ALERTNOAUTHOR'), 403);
		}
	}

}