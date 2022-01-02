<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\DataCompliance\Admin\Helper\Email;
use Akeeba\DataCompliance\Site\Model\Options;
use FOF40\Container\Container;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Log\Log;

defined('_JEXEC') or die;

/**
 * Data Compliance plugin for Akeeba Ticket System User Data
 */
class plgDatacomplianceEmail extends Joomla\CMS\Plugin\CMSPlugin
{
	protected $container;

	/**
	 * Constructor. Intializes the object:
	 * - Load the plugin's language strings
	 * - Get the com_datacompliance container
	 *
	 * @param   object  $subject  Passed by Joomla
	 * @param   array   $config   Passed by Joomla
	 */
	public function __construct($subject, array $config = [])
	{
		$this->autoloadLanguage = true;
		$this->container        = Container::getInstance('com_datacompliance');

		parent::__construct($subject, $config);
	}



}