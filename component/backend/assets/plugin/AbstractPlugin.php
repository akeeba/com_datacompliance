<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Log\Log;

defined('_JEXEC') or die;

/**
 * Data Compliance plugin for Core Joomla! User Data
 */
abstract class plgDatacomplianceAbstractPlugin extends Joomla\CMS\Plugin\CMSPlugin
{
	protected $container;

	/**
	 * Constructor. Intializes the object:
	 * - Load the plugin's language strings
	 * - Get the com_datacompliance container
	 *
	 * @param   object  $subject  Passed by Joomla
	 * @param   array   $config   Passed by Joomla
	 */
	public function __construct(&$subject, array $config = array())
	{
		$this->autoloadLanguage = true;
		$this->container = \FOF40\Container\Container::getInstance('com_datacompliance');

		parent::__construct($subject, $config);
	}

	/**
	 * Formats a number of bytes in human readable format
	 *
	 * @param   int  $size  The size in bytes to format, e.g. 8254862
	 *
	 * @return  string  The human-readable representation of the byte size, e.g. "7.87 Mb"
	 */
	protected function formatByteSize($size)
	{
		$unit	 = array('b', 'Kb', 'Mb', 'Gb', 'Tb', 'Pb');
		return @round($size / pow(1024, ($i	= floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
	}

	/**
	 * Returns the current memory usage, formatted
	 *
	 * @return  string
	 */
	protected function memUsage()
	{
		if (function_exists('memory_get_usage'))
		{
			$size	 = memory_get_usage();
			return $this->formatByteSize($size);
		}
		else
		{
			return "(unknown)";
		}
	}

	/**
	 * Get the FOF 3 or 4 Container of a FOF-powered component.
	 *
	 * The entry point file is analysed to see if the component is FOF 3 or 4, then the correct container is loaded.
	 *
	 * @param   string  $component
	 *
	 * @return  \FOF40\Container\Container|\FOF30\Container\Container|null
	 */
	protected function getFOFContainer(string $component, array $params = [], string $section = 'auto')
	{
		$bareComponent = (strpos($component, 'com_') === 0) ? substr($component, 4) : $component;
		$entryPoint = JPATH_ADMINISTRATOR . '/components/' . $component . '/' . $bareComponent . '.php';
		$contents = @file_get_contents($entryPoint);

		if ($contents === false)
		{
			return null;
		}

		if (strpos($contents, 'FOF40_INCLUDED') !== false)
		{
			return \FOF40\Container\Container::getInstance($component, $params, $section);
		}

		return \FOF30\Container\Container::getInstance($component, $params, $section);
	}
}