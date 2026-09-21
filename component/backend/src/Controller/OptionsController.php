<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Controller;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Exception\WipeRefusedException;
use Akeeba\Component\DataCompliance\Administrator\Mixin\ControllerEventsTrait;
use Akeeba\Component\DataCompliance\Administrator\Mixin\ControllerRegisterTasksTrait;
use Akeeba\Component\DataCompliance\Administrator\Mixin\ControllerReusableModelsTrait;
use Akeeba\Component\DataCompliance\Administrator\Model\ExportModel;
use Akeeba\Component\DataCompliance\Administrator\Model\OptionsModel;
use Akeeba\Component\DataCompliance\Administrator\Model\WipeModel;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\Route as JRoute;
use Joomla\CMS\Uri\Uri as JUri;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Input\Input;
use RuntimeException;

class OptionsController extends BaseController
{
	use ControllerEventsTrait;
	use ControllerRegisterTasksTrait;
	use ControllerReusableModelsTrait;

	public function __construct($config = [], ?MVCFactoryInterface $factory = null, ?CMSApplication $app = null, ?Input $input = null)
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
		$this->checkToken('post');
		$this->assertUserAccess('consent');

		$currentUser = $this->app->getIdentity();
		$userID      = $this->input->getInt('user_id', $currentUser->id);

		// Resolve the target user. Self-consent uses the current identity; otherwise load the requested user.
		if ($userID === $currentUser->id)
		{
			$user = $currentUser;
		}
		else
		{
			$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userID);
		}

		$enabled = $this->input->getBool('enabled', false);
		$reason  = $this->input->getString('reason', '');

		$defaultUrl = JRoute::_('index.php?option=com_datacompliance&view=options', false);
		$returnUrl  = $this->app->getSession()->get('com_datacompliance.return_url', $defaultUrl);

		if (!JUri::isInternal($returnUrl))
		{
			$returnUrl = $defaultUrl;
		}

		/** @var OptionsModel $model */
		$model = $this->getModel();

		try
		{
			$model->recordPreference($enabled, $user, $reason);
		}
		catch (RuntimeException $e)
		{
			$this->app->enqueueMessage($e->getMessage(), 'error');
			$this->setRedirect($returnUrl);
			$this->redirect();

			return;
		}

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
		$this->checkToken('post');
		$this->assertUserAccess('export');

		$currentUser = $this->app->getIdentity();
		$userID      = $this->input->getInt('user_id', $currentUser->id);

		// Make sure there's no buffered data
		@ob_end_clean();

		// Get the export data
		/** @var ExportModel $export */
		$export = $this->getModel('Export', 'Administrator');
		$result = $export->exportFormattedXML($userID);

		// Disable caching. The export contains personal data; no browser, proxy or CDN may store it.
		header("Pragma: no-cache");
		header("Expires: 0");
		header("Cache-Control: no-store, no-cache, must-revalidate, private");
		header("X-Content-Type-Options: nosniff");

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
		$this->assertUserAccess('wipe');

		/**
		 * Without a phrase we only display the confirmation page, which changes nothing; no anti-CSRF token is needed.
		 * This lets us link and redirect to it without putting the token in the URL. The phrase is only read from POST
		 * data, and the actual wipe requires a valid anti-CSRF token in the POST data.
		 */
		$phrase = $this->input->post->getString('phrase', null);

		if (!is_null($phrase))
		{
			$this->checkToken('post');
		}

		$currentUser = $this->app->getIdentity();
		$userID      = $this->input->getInt('user_id', $currentUser->id);
		$isCurrent   = $userID == $currentUser->id;

		$defaultUrl = JRoute::_('index.php?option=com_datacompliance&view=options', false);

		$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userID);
		/** @var WipeModel $wipeModel */
		$wipeModel = $this->getModel('Wipe', 'Administrator');

		// Can the user be wiped, at all?
		[$result, $error] = $this->safeWipeModelCall($wipeModel, 'checkWipeAbility', $user->id);

		if (!$result)
		{
			$msg         = Text::sprintf('COM_DATACOMPLIANCE_OPTIONS_WIPE_ERR_CANNOTBEERASED', $error);
			$url         = 'index.php?option=com_datacompliance&view=options';
			$url         .= empty($userID) ? '' : ('&user_id=' . $userID);
			$redirectUrl = JRoute::_($url, false);

			if (!JUri::isInternal($redirectUrl))
			{
				$redirectUrl = $defaultUrl;
			}

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
			$url         = 'index.php?option=com_datacompliance&view=options&task=wipe';
			$url         .= empty($userID) ? '' : ('&user_id=' . $userID);
			$redirectUrl = JRoute::_($url, false);

			if (!JUri::isInternal($redirectUrl))
			{
				$redirectUrl = $defaultUrl;
			}

			$this->setRedirect($redirectUrl, Text::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_ERR_BADPHRASE'), 'error');
			$this->redirect();

			return;
		}

		// Try to delete the user
		$currentUser = $this->app->getIdentity();
		$wipeType    = ($currentUser->id == $user->id) ? 'user' : 'admin';
		[$result, $error] = $this->safeWipeModelCall($wipeModel, 'wipe', $user->id, $wipeType);

		if (!$result)
		{
			$url         = 'index.php?option=com_datacompliance&view=options&task=wipe';
			$url         .= empty($userID) ? '' : ('&user_id=' . $userID);
			$redirectUrl = JRoute::_($url, false);

			if (!JUri::isInternal($redirectUrl))
			{
				$redirectUrl = $defaultUrl;
			}

			$message     = Text::sprintf('COM_DATACOMPLIANCE_OPTIONS_WIPE_ERR_DELETEFAILED', $error);
			$this->setRedirect($redirectUrl, $message, 'error');
			$this->redirect();

			return;
		}


		// Log out the now erased user
		if ($isCurrent)
		{
			$this->app->getSession()->close();
			$this->app->getSession()->destroy();
			$this->app->getSession()->restart();
			$this->app->getSession()->start();
		}

		// Redirect them to the home page
		$message = Text::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_MSG_ERASED');
		$this->app->enqueueMessage($message);
		$this->app->redirect(JUri::base());
	}

	/**
	 * Call a WipeModel method, making sure internal error details are never shown to the user.
	 *
	 * Only the reasons of a WipeRefusedException are meant for the user. Any other exception (e.g. a database error) is
	 * logged, and the user only sees a generic error message.
	 *
	 * @param   WipeModel  $model         The wipe model
	 * @param   string     $method        The method to call
	 * @param   mixed      ...$arguments  The method's arguments
	 *
	 * @return  array  [bool $result, string $error]
	 * @since   4.1.0
	 */
	private function safeWipeModelCall(WipeModel $model, string $method, ...$arguments): array
	{
		try
		{
			$result = $model->{$method}(...$arguments);
		}
		catch (WipeRefusedException $e)
		{
			return [false, $e->getMessage()];
		}
		catch (Exception $e)
		{
			self::logInternalError($e);

			return [false, Text::_('COM_DATACOMPLIANCE_OPTIONS_WIPE_ERR_INTERNAL')];
		}

		if ($result)
		{
			return [true, ''];
		}

		// Legacy error handling; the model may have recorded why it failed.
		$error = method_exists($model, 'getError') ? $model->getError() : '';

		return [false, is_string($error) ? $error : ''];
	}

	/**
	 * Log an internal error to the administrator/logs/com_datacompliance_errors.php log file.
	 *
	 * @param   Exception  $e  The exception to log
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	private static function logInternalError(Exception $e): void
	{
		static $hasLogger = false;

		if (!$hasLogger)
		{
			Log::addLogger(['text_file' => 'com_datacompliance_errors.php'], Log::ALL, ['com_datacompliance.errors']);

			$hasLogger = true;
		}

		Log::add(
			sprintf(
				'%s: %s in %s:%d%s%s',
				get_class($e),
				$e->getMessage(),
				$e->getFile(),
				$e->getLine(),
				PHP_EOL,
				$e->getTraceAsString()
			),
			Log::ERROR,
			'com_datacompliance.errors'
		);
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
		$isAdmin   = $user->authorise('core.admin', 'com_datacompliance');

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

			// Change a user's consent. Super Users and DataCompliance administrators can always do that.
			// Users with the dedicated `wipe` or `export` privilege can also do so, matching the
			// data-subject view in View/Options/HtmlView::populateBasicViewParameters.
			case 'consent':
				if (!$isSuper && !$isAdmin && !$canWipe && !$canExport)
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

				// Only Super Users may wipe other Super Users.
				if (!$isSuper && !empty($user_id))
				{
					$target = Factory::getContainer()
						->get(UserFactoryInterface::class)
						->loadUserById($user_id);
					if ($target->authorise('core.admin'))
					{
						throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
					}
				}

				break;

			// Export a user profile. Only Super Users, DataCompliance administrators and users with 'export' privilege.
			case 'export':
				if (!$canExport && !$isSuper && !$isAdmin)
				{
					throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
				}

				/**
				 * Only Super Users may export other Super Users.
				 *
				 * The export may contain credential material (see the Maximalist Export component option). Letting a
				 * lower privileged user export a Super User would make the export privilege equivalent to Super User.
				 */
				if (!$isSuper && !empty($user_id))
				{
					$target = Factory::getContainer()
						->get(UserFactoryInterface::class)
						->loadUserById($user_id);

					if ($target->authorise('core.admin'))
					{
						throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
					}
				}

				break;
		}
	}

}