<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Dispatcher;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Mixin\TriggerEventTrait;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\Document\HtmlDocument;
use RuntimeException;
use Throwable;

class Dispatcher extends ComponentDispatcher
{
	use TriggerEventTrait;

	protected $defaultController = 'controlpanel';

	public function dispatch()
	{
		try
		{
			if (version_compare(PHP_VERSION, '7.4.0', 'lt'))
			{
				throw new RuntimeException('Akeeba DataCompliance requires PHP 7.4.0 or later.');
			}

			$this->triggerEvent('onBeforeDispatch');

			parent::dispatch();

			// This will only execute if there is no redirection set by the Controller
			$this->triggerEvent('onAfterDispatch');
		}
		catch (Throwable $e)
		{
			$title = 'Akeeba Data Compliance';
			$isPro = false;

			// Frontend: forwards errors 401, 403 and 404 to Joomla
			if (in_array($e->getCode(), [401, 403, 404]) && $this->app->isClient('site'))
			{
				throw $e;
			}

			if (!(include_once JPATH_ADMINISTRATOR . '/components/com_datacompliance/tmpl/common/errorhandler.php'))
			{
				throw $e;
			}
		}
	}

	/** @inheritdoc  */
	protected function checkAccess()
	{
		// Always allow access to the options view
		if ($this->input->getCmd('view', null) === 'options')
		{
			return true;
		}

		// Check the user has permission to access this component if in the backend
		if ($this->app->isClient('administrator') && !$this->app->getIdentity()->authorise('core.manage', $this->option))
		{
			throw new NotAllowed($this->app->getLanguage()->_('JERROR_ALERTNOAUTHOR'), 403);
		}
	}

	protected function onBeforeDispatch()
	{
		$this->loadLanguage();

		$this->applyViewAndController();

		$this->loadCommonStaticMedia();
	}

	protected function loadLanguage(): void
	{
		$jLang = $this->app->getLanguage();

		$jLang->load($this->option, JPATH_ADMINISTRATOR);

		if (!$this->app->isClient('administrator'))
		{
			$jLang->load($this->option, JPATH_SITE);
		}
	}

	protected function applyViewAndController(): void
	{
		$controller = $this->input->getCmd('controller', null);
		$view       = $this->input->getCmd('view', null);
		$task       = $this->input->getCmd('task', 'main');

		if (strpos($task, '.') !== false)
		{
			// Explode the controller.task command.
			[$controller, $task] = explode('.', $task);
		}

		if (empty($controller) && empty($view))
		{
			$controller = $this->defaultController;
			$view       = $this->defaultController;
		}
		elseif (empty($controller) && !empty($view))
		{
			$view       = strtolower($view);
			$controller = $view;
		}
		elseif (!empty($controller) && empty($view))
		{
			$view = $controller;
		}

		$controller = strtolower($controller);
		$view       = strtolower($view);

		$this->input->set('view', $view);
		$this->input->set('controller', $controller);
		$this->input->set('task', $task);
	}

	private function loadCommonStaticMedia()
	{
		// Make sure we run under a CMS application
		if (!($this->app instanceof CMSApplication))
		{
			return;
		}

		// Make sure the document is HTML
		$document = $this->app->getDocument();

		if (!($document instanceof HtmlDocument))
		{
			return;
		}

		// Finally, load our 'common' preset
		$document->getWebAssetManager()
			->usePreset('com_datacompliance.backend');

	}
}