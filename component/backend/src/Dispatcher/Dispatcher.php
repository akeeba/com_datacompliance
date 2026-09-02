<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
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

	/**
	 * Minimum supported PHP version.
	 *
	 * @var string
	 */
	protected $minPHPVersion = '8.1.0';

	/**
	 * First PHP version which is NOT supported.
	 *
	 * @var string
	 */
	protected $maxPHPVersion = '8.7';

	public function dispatch()
	{
		if (version_compare(PHP_VERSION, $this->minPHPVersion, 'lt'))
		{
			throw new RuntimeException(
				sprintf(
					'Akeeba DataCompliance requires PHP %s or later.',
					$this->minPHPVersion
				)
			);
		}

		if (!empty($this->maxPHPVersion) && version_compare(PHP_VERSION, $this->maxPHPVersion, 'ge'))
		{
			throw new RuntimeException(
				sprintf(
					'Akeeba DataCompliance does not support PHP %s or later.',
					$this->maxPHPVersion
				)
			);
		}

		$this->triggerEvent('onBeforeDispatch');

		parent::dispatch();

		// This will only execute if there is no redirection set by the Controller
		$this->triggerEvent('onAfterDispatch');
	}

	/** @inheritdoc  */
	protected function checkAccess()
	{
		// Always allow access to the options view
		if ($this->input->getCmd('view', null) === 'options')
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