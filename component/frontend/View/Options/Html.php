<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Site\View\Options;

use Akeeba\DataCompliance\Site\Model\Options;
use Exception;
use FOF30\View\DataView\Html as HtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\User\User;

defined('_JEXEC') or die();

class Html extends HtmlView
{
	/**
	 * The article content for the privacy policy
	 *
	 * @var  string
	 */
	public $article;

	/**
	 * The user's current consent preference
	 *
	 * @var  bool
	 */
	public $preference;

	/**
	 * The site's name
	 *
	 * @var  string
	 */
	public $siteName;

	/**
	 * The Joomla! user object of the user we are going to be managing
	 *
	 * @var  User
	 */
	public $user;

	/**
	 * User profile deletion type
	 *
	 * @var  string
	 */
	public $type = 'user';

	/**
	 * Am I allowed to show the Export user profile controls?
	 *
	 * @var   bool
	 */
	public $showExport = false;

	/**
	 * Am I allowed to show the Wipe user profile controls?
	 *
	 * @var   bool
	 */
	public $showWipe = false;

	/**
	 * The human readable list of actions to be taken upon deleting a user's account
	 *
	 * @var  array
	 */
	public $bulletPoints = [];

	/**
	 * View the Data Options page
	 *
	 * @return  void
	 */
	protected function onBeforeOptions(): void
	{
		$this->populateBasicViewParameters();

		/** @var Options $model */
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
	}

	/**
	 * View the wipe confirmation page
	 *
	 * @return  void
	 */
	protected function onBeforeWipe(): void
	{
		$this->populateBasicViewParameters();

		/** @var Options $model */
		$model        = $this->getModel();
		$this->layout = 'wipe';

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
	 */
	private function populateBasicViewParameters(): void
	{
		// Only allow Super Users and DataCompliance users with Export or Wipe privileges to view a different user
		$currentUser = $this->container->platform->getUser();
		$canExport   = $currentUser->authorise('export', 'com_datacompliance');
		$canWipe     = $currentUser->authorise('wipe', 'com_datacompliance');
		$isSuper     = $currentUser->authorise('core.admin');
		$isAdmin     = $isSuper || $canWipe || $canExport;
		$userID      = $isAdmin ? $this->input->getInt('user_id', null) : null;

		$this->showExport = $this->container->params->get('showexport', 1);
		$this->showWipe   = $this->container->params->get('showwipe', 1);
		$this->user       = $this->container->platform->getUser($userID);
		$this->type       = ($this->user->id == $currentUser->id) ? 'user' : 'admin';

		if ($this->type == 'admin')
		{
			$this->showExport = $this->showExport && $canExport;
			$this->showWipe   = $this->showWipe && $canWipe;
		}
	}
}
