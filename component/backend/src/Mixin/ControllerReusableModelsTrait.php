<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Mixin;

defined('_JEXEC') || die;

use Exception;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\MVC\View\ViewInterface;

/**
 * A trait for reusing the models and views returned from getModel() and getView().
 *
 * Joomla's MVC is supposed to have a controller which allows you to push models to the view. However, the way it is
 * implemented means that every time you call getView you get a brand new instance of the view, with a blank state
 * default model. This makes it impossible to set up the default model before setting up the view. This trait addresses
 * this problem by making getModel() and getView() work in the more logical and consistent manner we are used to from
 * FOF.
 */
trait ControllerReusableModelsTrait
{
	static $_models = [];

	public function getModel($name = '', $prefix = '', $config = [])
	{
		if (empty($name))
		{
			$name = ucfirst($this->input->get('view', $this->default_view));
		}

		$prefix = ucfirst($prefix ?: $this->app->getName());

		$hash = md5(strtolower($name . $prefix));

		if (isset(self::$_models[$hash]))
		{
			return self::$_models[$hash];
		}

		self::$_models[$hash] = parent::getModel($name, $prefix, $config);

		return self::$_models[$hash];
	}

	/**
	 * @param   string  $name
	 * @param   string  $type
	 * @param   string  $prefix
	 * @param   array   $config
	 *
	 * @return ViewInterface|HtmlView
	 * @throws Exception
	 */
	public function getView($name = '', $type = '', $prefix = '', $config = [])
	{
		$document = $this->app->getDocument();

		if (empty($name))
		{
			$name = $this->input->get('view', $this->default_view);
		}

		if (empty($type))
		{
			$type = $document->getType();
		}

		if (empty($config))
		{
			$viewLayout = $this->input->get('layout', 'default', 'string');
			$config     = ['base_path' => $this->basePath, 'layout' => $viewLayout];
		}

		$hadView = isset(self::$views)
			&& isset(self::$views[$name])
			&& isset(self::$views[$name][$type])
			&& isset(self::$views[$name][$type][$prefix])
			&& !empty(self::$views[$name][$type][$prefix]);

		$view = parent::getView($name, $type, $prefix, $config);

		if (!$hadView)
		{
			// Get/Create the model
			if ($model = $this->getModel($name, 'Administrator', ['base_path' => $this->basePath]))
			{
				// Push the model into the view (as default)
				$view->setModel($model, true);
			}

			$view->document = $document;
		}

		return $view;
	}
}