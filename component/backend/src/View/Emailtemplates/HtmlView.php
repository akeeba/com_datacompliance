<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\View\Emailtemplates;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
	public function display($tpl = null)
	{
		ToolbarHelper::title(sprintf(Text::_('COM_DATACOMPLIANCE_TITLE_EMAILTEMPLATES')), 'icon-datacompliance');
		ToolbarHelper::back('JTOOLBAR_BACK', Route::_('index.php?option=com_datacompliance&view=Controlpanel', false));

		parent::display($tpl);
	}

}