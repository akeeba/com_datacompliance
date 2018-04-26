<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Site\View\Options;

defined('_JEXEC') or die();

use Akeeba\DataCompliance\Site\Model\Options;
use FOF30\View\DataView\Html as HtmlView;
use Joomla\CMS\Factory;

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
	 * The human readable list of actions to be taken upon deleting a user's account
	 *
	 * @var  array
	 */
	public $bulletPoints = [];

	protected function onBeforeOptions()
	{
		$this->layout     = 'default';
		$this->article    = $this->get('article');
		$this->preference = $this->get('preference');
		$this->siteName   = Factory::getApplication()->get('sitename', '');
	}

	protected function onBeforeWipe()
	{
		/** @var Options $model */
		$model              = $this->getModel();
		$this->layout       = 'wipe';
		$this->bulletPoints = $model->getBulletPoints();
	}

}