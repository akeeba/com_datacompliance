<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Akeeba\Plugin\DataCompliance\S3\Extension\S3;

return new class implements ServiceProviderInterface {
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 *
	 * @since   9.0.0
	 */
	public function register(Container $container)
	{
		$mvcFactory = new MVCFactory('Akeeba\\Component\\DataCompliance');
		$container->registerServiceProvider($mvcFactory);

		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$params     = (array) PluginHelper::getPlugin('datacompliance', 's3');
				$dispatcher = $container->get(DispatcherInterface::class);

				$plugin = new S3(
					$dispatcher, $params, new \Joomla\CMS\MVC\Factory\MVCFactory('Akeeba\\Component\\DataCompliance')
				);

				$plugin->setApplication(Factory::getApplication());
				$plugin->setDatabase($container->get(DatabaseInterface::class));

				return $plugin;
			}
		);
	}
};
