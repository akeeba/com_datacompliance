<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
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
	 * Am I allowed to show the Export user profile controls?
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	public $showExport = false;

	/**
	 * Am I allowed to show the Wipe user profile controls?
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	public $showWipe = false;

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

		$this->document->getWebAssetManager()
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
	 * Populate basic view parameters such as showExport, showWipe, user and type
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   1.0.0
	 */
	private function populateBasicViewParameters(): void
	{
		// Only allow Super Users and DataCompliance users with Export or Wipe privileges to view a different user
		$currentUser = Factory::getApplication()->getIdentity();
		$canExport   = $currentUser->authorise('export', 'com_datacompliance');
		$canWipe     = $currentUser->authorise('wipe', 'com_datacompliance');
		$isSuper     = $currentUser->authorise('core.admin');
		$isAdmin     = $isSuper || $canWipe || $canExport;
		$userID      = $isAdmin ? Factory::getApplication()->input->getInt('user_id', null) : null;
		$cParams     = ComponentHelper::getParams('com_datacompliance');

		$this->showExport = $cParams->get('showexport', 1);
		$this->showWipe   = $cParams->get('showwipe', 1);
		$this->user       = empty($userID)
			? Factory::getApplication()->getIdentity()
			: Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userID);
		$this->type       = ($this->user->id == $currentUser->id) ? 'user' : 'admin';

		if ($this->type == 'admin')
		{
			$this->showExport = $this->showExport && $canExport;
			$this->showWipe   = $this->showWipe && $canWipe;
		}

		if (Factory::getApplication()->isClient('administrator'))
		{
			ToolbarHelper::title(Text::_('COM_DATACOMPLIANCE_OPTIONS_CONSENT_HEADER'), 'datacompliance');
		}
	}
}