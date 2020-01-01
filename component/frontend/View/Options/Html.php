<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Site\View\Options;

use Akeeba\DataCompliance\Site\Model\Options;
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
	 * The human readable list of actions to be taken upon deleting a user's account
	 *
	 * @var  array
	 */
	public $bulletPoints = [];

	protected function onBeforeOptions()
	{
		$this->populateUser();

		$this->layout     = 'default';
		$this->article    = $this->get('article');
		$this->preference = $this->get('preference');
		$this->siteName   = Factory::getApplication()->get('sitename', '');
	}

	protected function onBeforeWipe()
	{
		$this->populateUser();

		/** @var Options $model */
		$model              = $this->getModel();
		$this->layout       = 'wipe';
		$this->bulletPoints = $model->getBulletPoints($this->user, $this->type);
	}

	private function populateUser()
	{
		$userID      = $this->input->getInt('user_id', null);

		$this->showExport = $this->container->params->get('showexport', 1);
		$this->showWipe   = $this->container->params->get('showwipe', 1);
		$this->user       = $this->container->platform->getUser($userID);
		$this->type       = ($this->user->id == $this->container->platform->getUser()->id) ? 'user' : 'admin';
	}
}
