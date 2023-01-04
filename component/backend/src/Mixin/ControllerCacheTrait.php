<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Mixin;

use Joomla\CMS\Cache\CacheController;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;

defined('_JEXEC') or die;

/**
 * Trait for using a cache controller inside the MVC controller.
 *
 * @since  3.0.0
 */
trait ControllerCacheTrait
{
	/**
	 * Already created Joomla cache controllers
	 *
	 * @var  array
	 */
	protected static $cacheControllers = [];

	/**
	 * Get a cache controller
	 *
	 * @param   string       $group    Caching group. Defaults to the currently displayed component.
	 * @param   string       $handler  Cache handler. Default to 'callback'.
	 * @param   string|null  $storage  Cache storage engine. Leave null to use Joomla! default.
	 *
	 * @return CacheController
	 * @since  3.0.0
	 */
	protected function getCache(string $group = '', string $handler = 'callback', ?string $storage = null): CacheController
	{
		$group = $group ?: $this->input->get('option', 'com_datacompliance');

		$hash = md5($group . $handler . $storage);

		if (isset(self::$cacheControllers[$hash]))
		{
			return self::$cacheControllers[$hash];
		}

		$options = ['defaultgroup' => $group];
		if (isset($storage))
		{
			$options['storage'] = $storage;
		}

		$cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)
			->createCacheController($handler, $options);

		self::$cacheControllers[$hash] = $cache;

		return self::$cacheControllers[$hash];
	}
}