<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Controller;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Helper\TemplateEmails;
use Akeeba\Component\DataCompliance\Administrator\Mixin\ControllerEventsTrait;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

class EmailtemplatesController extends BaseController
{
	use ControllerEventsTrait;

	/**
	 * Defence in depth: the dispatcher already enforces core.manage, but these tasks overwrite the component's mail
	 * templates. Do not rely on the dispatcher alone.
	 *
	 * @param   string  $task  The task being executed
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function onBeforeExecute(&$task): void
	{
		if (!$this->app->getIdentity()->authorise('core.manage', 'com_datacompliance'))
		{
			throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
	}

	public function updateEmails($cachable = false, $urlparams = [])
	{
		$this->checkToken('post');

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
		$this->checkToken('post');

		$returnURL = Route::_('index.php?option=com_datacompliance&view=Emailtemplates', false);
		$this->setRedirect($returnURL);

		$affected = TemplateEmails::resetAllTemplates();

		$message = ($affected > 0) ?
			Text::plural('COM_DATACOMPLIANCE_EMAILTEMPLATES_LBL_N_RESET', $affected) :
			Text::_('COM_DATACOMPLIANCE_EMAILTEMPLATES_ERR_RESET');

		$this->setMessage($message, ($affected > 0) ? 'success' : 'error');
	}

}