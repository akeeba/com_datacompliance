<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\View\Options;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Mixin\ViewLoadAnyTemplateTrait;
use Akeeba\Component\DataCompliance\Administrator\Mixin\ViewTaskBasedEventsTrait;
use Akeeba\Component\DataCompliance\Administrator\Model\OptionsModel;
use Exception;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;

class HtmlView extends BaseHtmlView
{
	/**
	 * The article content for the privacy policy
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	public $article;

	/**
	 * The human readable list of actions to be taken upon deleting a user's account
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	public $bulletPoints = [];

	/**
	 * The user's current consent preference
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	public $preference;

	/**
	 * Show the Export controls on the user's OWN Options page (self-service)?
	 *
	 * Driven by the component's showexport option. It does NOT apply when managing another user; see $canManageExport.
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	public $showExport = false;

	/**
	 * Show the Wipe controls on the user's OWN Options page (self-service)?
	 *
	 * Driven by the component's showwipe option. It does NOT apply when managing another user; see $canManageWipe.
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	public $showWipe = false;

	/**
	 * Show the Export button when managing ANOTHER user's Options page?
	 *
	 * Deliberately independent of the component's showexport option: administrators must always be able to export
	 * other users (compliance requirement).
	 *
	 * @var   bool
	 * @since 4.1.0
	 */
	public $canManageExport = false;

	/**
	 * Show the Delete button when managing ANOTHER user's Options page?
	 *
	 * Deliberately independent of the component's showwipe option: administrators must always be able to delete
	 * other users (compliance requirement).
	 *
	 * @var   bool
	 * @since 4.1.0
	 */
	public $canManageWipe = false;

	/**
	 * Can the current user record consent on behalf of the user being displayed?
	 *
	 * @var   bool
	 * @since 4.1.0
	 */
	public $canManageConsent = false;

	/**
	 * The site's name
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	public $siteName;

	/**
	 * User profile deletion type
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	public $type = 'user';

	/**
	 * The Joomla! user object of the user we are going to be managing
	 *
	 * @var   User
	 * @since 1.0.0
	 */
	public $user;

	use ViewTaskBasedEventsTrait;
	use ViewLoadAnyTemplateTrait;

	/**
	 * View the Data Options page
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   1.0.0
	 */
	protected function onBeforeMain(): void
	{
		$this->populateBasicViewParameters();

		/** @var OptionsModel $model */
		$model            = $this->getModel();
		$this->layout     = 'default';
		$this->article    = $model->getArticle();
		$this->preference = $model->getPreference($this->user);

		try
		{
			$this->siteName = Factory::getApplication()->get('sitename', '');
		}
		catch (Exception $e)
		{
			$this->siteName = '(Unknown site)';
		}

		$this->getDocument()->getWebAssetManager()
			->useScript('com_datacompliance.options');
	}

	/**
	 * View the wipe confirmation page
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	protected function onBeforeWipe(): void
	{
		$this->populateBasicViewParameters();

		/** @var OptionsModel $model */
		$model        = $this->getModel();

		$this->setLayout('wipe');

		try
		{
			$this->bulletPoints = $model->getBulletPoints($this->user, $this->type);
		}
		catch (Exception $e)
		{
			$this->bulletPoints = [];
		}
	}

	/**
	 * Populate basic view parameters such as showExport, showWipe, canManageExport, canManageWipe, user and type
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   1.0.0
	 */
	private function populateBasicViewParameters(): void
	{
		/**
		 * Only allow Super Users, DataCompliance administrators (core.admin on the component), and DataCompliance users
		 * with Export or Wipe privileges to view a different user. Mirrors OptionsController::assertUserAccess('options').
		 */
		$currentUser = Factory::getApplication()->getIdentity();
		$canExport   = $currentUser->authorise('export', 'com_datacompliance');
		$canWipe     = $currentUser->authorise('wipe', 'com_datacompliance');
		$isSuper     = $currentUser->authorise('core.admin');
		$isDCAdmin   = $currentUser->authorise('core.admin', 'com_datacompliance');
		$isAdmin     = $isSuper || $isDCAdmin || $canWipe || $canExport;
		$userID      = $isAdmin ? Factory::getApplication()->getInput()->getInt('user_id', null) : null;
		$cParams     = ComponentHelper::getParams('com_datacompliance');

		// Self-service only: these component options govern the buttons on the user's own Options page.
		$this->showExport = (bool) $cParams->get('showexport', 1);
		$this->showWipe   = (bool) $cParams->get('showwipe', 1);
		$this->user       = empty($userID)
			? Factory::getApplication()->getIdentity()
			: Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userID);
		$this->type       = ($this->user->id == $currentUser->id) ? 'user' : 'admin';

		// Mirrors OptionsController::assertUserAccess('consent')
		$this->canManageConsent = $isSuper || $isDCAdmin;

		/**
		 * Managing another user. HAVING THE EXPORT / DELETE BUTTONS TRUMPS EVERY OTHER DISPLAY OPTION.
		 *
		 * An administrator must always be able to export and delete other users; a UI where they cannot is a compliance
		 * problem. Therefore these flags depend ONLY on the viewer's privileges, exactly as
		 * OptionsController::assertUserAccess() checks them. The component's showexport / showwipe options must NOT be
		 * applied here; they only govern a user's own self-service buttons.
		 *
		 * The one exception: only Super Users may export or wipe other Super Users (H2a). The controller refuses that
		 * action, so the button could never work and is not shown.
		 */
		if ($this->type == 'admin')
		{
			$targetIsSuper = $this->user->authorise('core.admin');

			$this->canManageExport = ($canExport || $isSuper || $isDCAdmin) && ($isSuper || !$targetIsSuper);
			$this->canManageWipe   = ($canWipe || $isSuper || $isDCAdmin) && ($isSuper || !$targetIsSuper);
		}

		if (Factory::getApplication()->isClient('administrator'))
		{
			ToolbarHelper::title(Text::_('COM_DATACOMPLIANCE_OPTIONS_CONSENT_HEADER'), 'datacompliance');
		}
	}
}