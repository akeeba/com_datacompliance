<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') || die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Console\DataCompliance\Extension\DataCompliance;

// Make sure that Joomla has registered the namespace for the plugin
if (!class_exists('\Joomla\Plugin\Console\DataCompliance\Extension\DataCompliance'))
{
	JLoader::registerNamespace('\Joomla\Plugin\Console\DataCompliance', realpath(__DIR__ . '/../src'));
}

return new class implements ServiceProviderInterface {
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 *
	 * @since   3.0.0
	 */
	public function register(Container $container)
	{
		$container->registerServiceProvider(new MVCFactory('Akeeba\\Component\\DataCompliance'));

		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$config     = (array) PluginHelper::getPlugin('console', 'datacompliance');
				$subject    = $container->get(DispatcherInterface::class);
				$mvcFactory = $container->get(MVCFactoryInterface::class);

				$plugin = new DataCompliance($subject, $config, $mvcFactory);

				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			}
		);
	}
};
