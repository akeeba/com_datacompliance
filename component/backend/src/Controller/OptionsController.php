<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Controller;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Controller\Mixin\ControllerEvents;
use Akeeba\Component\DataCompliance\Administrator\Controller\Mixin\RegisterControllerTasks;
use Akeeba\Component\DataCompliance\Administrator\Controller\Mixin\ReusableModels;
use Akeeba\Component\DataCompliance\Administrator\Model\ExportModel;
use Akeeba\Component\DataCompliance\Administrator\Model\OptionsModel;
use Akeeba\Component\DataCompliance\Administrator\Model\WipeModel;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\Route as JRoute;
use Joomla\CMS\Uri\Uri as JUri;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Input\Input;
use RuntimeException;

class OptionsController extends BaseController
{
	use ControllerEvents;
	use RegisterControllerTasks;
	use ReusableModels;

	public function __construct($config = [], MVCFactoryInterface $factory = null, ?CMSApplication $app = null, ?Input $input = null)
	{
		parent::__construct($config, $factory, $app, $input);

		$this->registerControllerTasks('options');
	}

	/**
	 * Apply the personal data consent preferences
	 *
	 * @param   string  $tpl
	 *
	 * @throws  Exception
	 *
	 * @since   1.0.0
	 */
	public function consent()
	{
		$this->checkToken($this->input->getMethod());
		$this->assertUserAccess('consent');

		/** @var OptionsModel $model */
		$model = $this->getModel();

		$model->recordPreference($this->input->getBool('enabled', false));

		$defaultUrl = JRoute::_('index.php?option=com_datacompliance&view=Options', false);
		$returnUrl  = $this->app->getSession()->get('com_datacompliance.return_url', $defaultUrl);

		$message = Text::_('COM_DATACOMPLIANCE_OPTIONS_CONSENT_MSG_RECORDED');
		$this->setRedirect($returnUrl, $message);
		$this->redirect();
	}

	/**
	 * Export the personal data profile
	 *
	 * @param   string  $tpl
	 *
	 * @throws  Exception
	 *
	 * @since   1.0.0
	 */
	public function export()
	{
		$this->checkToken($this->input->getMethod());
		$this->assertUserAccess('export');

		$currentUser = $this->app->getIdentity();
		$userID      = $this->input->getInt('user_id', $currentUser->id);

		// Make sure there's no buffered data
		@ob_end_clean();

		// Get the export data
		/** @var ExportModel $export */
		$export = $this->getModel('Export', 'Administrator');
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

		$this->app->close();
	}

	/**
	 * Default task, shows a page for the user to make their data protection options.
	 *
	 * @param   string  $tpl
	 *
	 * @since   1.0.0
	 */
	public function options()
	{
		$this->assertUserAccess('options');

		$this->display(false, []);
	}

	/**
	 * Wipe the user's profile (ask for confirmation first)
	 *
	 * @param   string  $tpl
	 *
	 * @throws  Exception
	 *
	 * @since   1.0.0
	 */
	public function wipe()
	{
		$this->checkToken($this->input->getMethod());
		$this->assertUserAccess('wipe');

		$currentUser = $this->app->getIdentity();
		$userID      = $this->input->getInt('user_id', $currentUser->id);
		$isCurrent   = $userID == $currentUser->id;

		$phrase = $this->input->getString('phrase', null);
		$user   = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userID);
		/** @var WipeModel $wipeModel */
		$wipeModel = $this->getModel('Wipe', 'Administrator');

		// Can the user be wiped, at all?
		if (!$wipeModel->checkWipeAbility($user->id))
		{
			$msg         = Text::sprintf('COM_DATACOMPLIANCE_OPTIONS_WIPE_ERR_CANNOTBEERASED', $wipeModel->getError());
			$url         = 'index.php?option=com_datacompliance&view=Options';
			$url         .= empty($userID) ? '' : ('&user_id=' . $userID);
			$redirectUrl = JRoute::_($url, false);
			$this->setRedirect($redirectUrl, $msg, 'error');
			$this->redirect();
		}

		// If 'phrase' is not set just skip to displaying the confirmation interface
		if (is_null($phrase))
		{
			$this->display(false, []);

			return;
		}

		// Confirm the phrase
		if ($phrase != Text::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_CONFIRMPHRASE'))
		{
			$token       = $this->app->getFormToken();
			$url         = 'index.php?option=com_datacompliance&view=Options&task=wipe&' . $token . '=1';
			$url         .= empty($userID) ? '' : ('&user_id=' . $userID);
			$redirectUrl = JRoute::_($url, false);
			$this->setRedirect($redirectUrl, Text::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_ERR_BADPHRASE'), 'error');
			$this->redirect();

			return;
		}

		// Try to delete the user
		$currentUser = $this->app->getIdentity();
		$wipeType    = ($currentUser->id == $user->id) ? 'user' : 'admin';
		$result      = $wipeModel->wipe($user->id, $wipeType);

		if (!$result)
		{
			$token       = $this->app->getFormToken();
			$url         = 'index.php?option=com_datacompliance&view=Options&task=wipe&' . $token . '=1';
			$url         .= empty($userID) ? '' : ('&user_id=' . $userID);
			$redirectUrl = JRoute::_($url, false);
			$message     = Text::sprintf('COM_DATACOMPLIANCE_OPTIONS_WIPE_ERR_DELETEFAILED', $wipeModel->getError());
			$this->setRedirect($redirectUrl, $message, 'error');
			$this->redirect();

			return;
		}


		// Log out the now erased user
		if ($isCurrent)
		{
			$this->app->getSession()->close();
			$this->app->getSession()->restart();
		}

		// Redirect them to the home page
		$message = Text::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_MSG_ERASED');
		$this->app->enqueueMessage($message);
		$this->app->redirect(JUri::base());
	}

	/**
	 * Ensures that the user has adequate access to fulfil the request.
	 *
	 * @return   void
	 *
	 * @throws   RuntimeException  If access is not allowed
	 * @since    1.0.0
	 *
	 */
	private function assertUserAccess($actionType = 'options')
	{
		// Make sure there is a logged in user
		$user = $this->app->getIdentity();

		if ($user->guest)
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
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
		$isSuper   = $user->authorise('core.admin');
		$isAdmin   = $user->authorise('core.manage', 'com_datacompliance');

		switch ($actionType)
		{
			// View the Options page. Any privilege will do.
			default:
			case 'options':
				if (!$canExport && !$canWipe && !$isSuper && !$isAdmin)
				{
					throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
				}
				break;

			// Change a user's consent. Only Super Users and DataCompliance administrators are allowed to do that.
			case 'consent':
				if (!$isSuper && !$isAdmin)
				{
					throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
				}
				break;

			// Delete a user profile. Only Super Users, DataCompliance administrators and users with 'wipe' privilege.
			case 'wipe':
				if (!$canWipe && !$isSuper && !$isAdmin)
				{
					throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
				}

				break;

			// Export a user profile. Only Super Users, DataCompliance administrators and users with 'export' privilege.
			case 'export':
				if (!$canExport && !$isSuper && !$isAdmin)
				{
					throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
				}

				break;
		}

		if (!$canExport && !$canWipe)
		{
			// Neither privilege is granted. You are trying to do something naughty.
			throw new RuntimeException(JText::_('JERROR_ALERTNOAUTHOR'), 403);
		}
	}

}