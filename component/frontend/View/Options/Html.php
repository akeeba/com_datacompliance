<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Site\View\Options;

defined('_JEXEC') or die();

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

	protected function onBeforeOptions()
	{
		$this->article    = $this->get('article');
		$this->preference = $this->get('preference');
		$this->siteName   = Factory::getApplication()->get('sitename', '');
	}


}