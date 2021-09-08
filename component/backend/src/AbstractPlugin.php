<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator;

defined('_JEXEC') or die;

use Joomla\Application\AbstractApplication;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\DispatcherInterface;

/**
 * Formerly used as the common class for all DataCompliance plugins
 *
 * @since  3.0.0
 * @deprecated 4.0
 */
abstract class AbstractPlugin extends CMSPlugin
{
	/** @var AbstractApplication */
	protected $app;

	/** @var DatabaseDriver */
	protected $db;

	/**
	 * Constructor.
	 *
	 * @param   DispatcherInterface  $subject  Passed by Joomla
	 * @param   array                $config   Passed by Joomla
	 *
	 * @since   3.0.0
	 */
	public function __construct(DispatcherInterface &$subject, array $config = [])
	{
		$this->autoloadLanguage = true;

		parent::__construct($subject, $config);
	}

	/**
	 * Formats a number of bytes in human readable format
	 *
	 * @param   int  $size  The size in bytes to format, e.g. 8254862
	 *
	 * @return  string  The human-readable representation of the byte size, e.g. "7.87 Mb"
	 *
	 * @since   3.0.0
	 */
	protected function formatByteSize(int $size): string
	{
		$unit = ['b', 'Kb', 'Mb', 'Gb', 'Tb', 'Pb'];

		return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
	}

	/**
	 * Returns the current memory usage, formatted human readable
	 *
	 * @return  string
	 */
	protected function memUsage(): string
	{
		if (function_exists('memory_get_usage'))
		{
			$size = memory_get_usage();

			return $this->formatByteSize($size);
		}
		else
		{
			return "(unknown)";
		}
	}
}