<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\View\Controlpanel;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Mixin\ViewLoadAnyTemplateTrait;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
	use ViewLoadAnyTemplateTrait;

	public function display($tpl = null)
	{
		$this->document->getWebAssetManager()
			->useScript('com_datacompliance.controlpanel')
			->useScript('com_datacompliance.chart_moment_adapter');

		$this->document->addScriptOptions(
			'com_datacompliance.controlpanel.userGraphsUrl',
			Route::_('index.php?option=com_datacompliance&task=controlpanel.userstats', false, Route::TLS_IGNORE, true)
		);
		$this->document->addScriptOptions(
			'com_datacompliance.controlpanel.wipedGraphsUrl',
			Route::_('index.php?option=com_datacompliance&task=controlpanel.wipedstats', false, Route::TLS_IGNORE, true)
		);

		Text::script('COM_DATACOMPLIANCE_CONTROLPANEL_LBL_CHART_INACTIVE');
		Text::script('COM_DATACOMPLIANCE_CONTROLPANEL_LBL_CHART_ACTIVE');
		Text::script('COM_DATACOMPLIANCE_CONTROLPANEL_LBL_CHART_DELETED');
		Text::script('COM_DATACOMPLIANCE_CONTROLPANEL_LBL_CHART_USER');
		Text::script('COM_DATACOMPLIANCE_CONTROLPANEL_LBL_CHART_ADMIN');
		Text::script('COM_DATACOMPLIANCE_CONTROLPANEL_LBL_CHART_LIFECYCLE');

		ToolbarHelper::title(Text::_('COM_DATACOMPLIANCE_TITLE_DASHBOARD'), 'datacompliance');
		ToolbarHelper::preferences('com_datacompliance');

		parent::display($tpl);
	}

}