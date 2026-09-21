<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Dispatcher;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Helper\VersionLimits;
use Akeeba\Component\DataCompliance\Administrator\Mixin\TriggerEventTrait;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\Document\HtmlDocument;
use LogicException;
use Throwable;

class Dispatcher extends ComponentDispatcher
{
	use TriggerEventTrait;

	protected $defaultController = 'controlpanel';

	public function dispatch()
	{
		// Check the supported PHP and Joomla version limits
		VersionLimits::throwIfVersionsIncompatible();

		$this->triggerEvent('onBeforeDispatch');

		parent::dispatch();

		// This will only execute if there is no redirection set by the Controller
		$this->triggerEvent('onAfterDispatch');
	}

	/** @inheritdoc  */
	protected function checkAccess()
	{
		/**
		 * Expected flow. This method relies on it; do not reorder it.
		 *
		 * 1. Our dispatch() triggers onBeforeDispatch, which calls applyViewAndController(). That method ALWAYS sets a
		 *    non-empty `controller` input, using the first of: the prefix of a `controller.task` task; an explicit
		 *    `controller` URL parameter; the view name; the default controller. It also sets `view` and `task`.
		 * 2. Our dispatch() then calls parent::dispatch(). Core's ComponentDispatcher::dispatch() calls this method
		 *    first, then instantiates the controller named in the `controller` input (NOT the view).
		 *
		 * Therefore, the `controller` input we read here is the controller which is about to run. If it's empty, the flow
		 * above has been broken by a code change. That's a bug in this component, not a runtime condition.
		 */
		$controller = $this->input->getCmd('controller', null);

		if (empty($controller))
		{
			throw new LogicException(
				sprintf(
					'%s::checkAccess() called without a controller; applyViewAndController() must run first.',
					static::class
				)
			);
		}

		/**
		 * Always allow access to the options view, but only when it's also the controller which will run.
		 *
		 * Checking the view alone would let view=options&task=anothercontroller.task run another controller without
		 * core.manage.
		 */
		if (
			$this->input->getCmd('view', null) === 'options'
			&& $controller === 'options'
		)
		{
			return true;
		}

		// In the backend, only users with the core.manage privilege may access any other view.
		if ($this->app->isClient('administrator'))
		{
			if (!$this->app->getIdentity()->authorise('core.manage', $this->option))
			{
				throw new NotAllowed($this->app->getLanguage()->_('JERROR_ALERTNOAUTHOR'), 403);
			}

			return true;
		}

		// Fail closed on the frontend: the 'options' view above is the only publicly reachable view.
		throw new NotAllowed($this->app->getLanguage()->_('JERROR_ALERTNOAUTHOR'), 403);
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

		$document->getWebAssetManager()
			->useStyle('com_datacompliance.j5');
	}
}