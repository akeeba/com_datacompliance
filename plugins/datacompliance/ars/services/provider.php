<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\DataCompliance\ARS\Extension\ARS;

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
				$config     = (array) PluginHelper::getPlugin('datacompliance', 'ars');
				$dispatcher = $container->get(DispatcherInterface::class);

				$plugin = new ARS(
					$dispatcher, $config, new \Joomla\CMS\MVC\Factory\MVCFactory('Akeeba\\Component\\DataCompliance')
				);

				$plugin->setApplication(Factory::getApplication());
				$plugin->setDatabase($container->get('DatabaseDriver'));

				return $plugin;
			}
		);
	}
};
