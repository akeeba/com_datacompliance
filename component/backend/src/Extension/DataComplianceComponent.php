<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Extension;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Service\Html\DataCompliance;
use Joomla\CMS\Categories\CategoryServiceInterface;
use Joomla\CMS\Categories\CategoryServiceTrait;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Psr\Container\ContainerInterface;

class DataComplianceComponent extends MVCComponent implements
	BootableExtensionInterface, CategoryServiceInterface, RouterServiceInterface
{
	use HTMLRegistryAwareTrait;
	use RouterServiceTrait;
	use CategoryServiceTrait;

	public function boot(ContainerInterface $container)
	{
		// Register the HTML helper
		$dbo = $container->get('DatabaseDriver');
		$this->getRegistry()->register('datacompliance', new DataCompliance($dbo));

		// Make sure the Composer autoloader for our dependencies is loaded
		require_once __DIR__ . '/../../vendor/autoload.php';
	}
}