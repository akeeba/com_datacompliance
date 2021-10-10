<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Controller;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Controller\Mixin\ControllerEvents;
use Akeeba\Component\DataCompliance\Administrator\Helper\TemplateEmails;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use RuntimeException;

class EmailtemplatesController extends BaseController
{
	use ControllerEvents;

	public function updateEmails($cachable = false, $urlparams = [])
	{
		if (!$this->checkToken('get'))
		{
			throw new RuntimeException(Text::_('JINVALID_TOKEN'), 403);
		}

		$returnURL = Route::_('index.php?option=com_datacompliance&view=Emailtemplates', false);
		$this->setRedirect($returnURL);

		$affected = TemplateEmails::updateAllTemplates();

		$message = ($affected > 0) ?
			Text::plural('COM_DATACOMPLIANCE_EMAILTEMPLATES_LBL_N_UPDATED', $affected) :
			Text::_('COM_DATACOMPLIANCE_EMAILTEMPLATES_ERR_NOUPDATE');

		$this->setMessage($message, ($affected > 0) ? 'success' : 'warning');
	}

	public function resetEmails($cachable = false, $urlparams = [])
	{
		if (!$this->checkToken('get'))
		{
			throw new RuntimeException(Text::_('JINVALID_TOKEN'), 403);
		}

		$returnURL = Route::_('index.php?option=com_datacompliance&view=Emailtemplates', false);
		$this->setRedirect($returnURL);

		$affected = TemplateEmails::resetAllTemplates();

		$message = ($affected > 0) ?
			Text::plural('COM_DATACOMPLIANCE_EMAILTEMPLATES_LBL_N_RESET', $affected) :
			Text::_('COM_DATACOMPLIANCE_EMAILTEMPLATES_ERR_RESET');

		$this->setMessage($message, ($affected > 0) ? 'success' : 'error');
	}

}