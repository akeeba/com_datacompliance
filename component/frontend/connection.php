<?php
/**
 * @package   Akeeba Connection
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

JDEBUG ? define('AKEEBADEBUG', 1) : null;

if (version_compare(PHP_VERSION, '5.4.0', 'lt'))
{
	// Minimum PHP requierment not met; pretend this component does not exist
	throw new RuntimeException(JText::_('JLIB_APPLICATION_ERROR_COMPONENT_NOT_FOUND'), 404);
}

if (!defined('FOF30_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof30/include.php'))
{
	throw new RuntimeException('FOF 3.0 is not installed', 500);
}

FOF30\Container\Container::getInstance('com_connection')->dispatcher->dispatch();
