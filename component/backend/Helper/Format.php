<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Helper;

use DateTimeZone;
use FOF30\Container\Container;
use FOF30\Date\Date;
use Joomla\CMS\Factory;

defined('_JEXEC') or die;

abstract class Format
{
	/**
	 * Format a date for display.
	 *
	 * The $tzAware parameter defines whether the formatted date will be timezone-aware. If set to false the formatted
	 * date will be rendered in the UTC timezone. If set to true the code will automatically try to use the logged in
	 * user's timezone or, if none is set, the site's default timezone (Server Timezone). If set to a positive integer
	 * the same thing will happen but for the specified user ID instead of the currently logged in user.
	 *
	 * @param   string    $date     The date to format
	 * @param   string    $format   The format string, default is whatever you specified in the component options
	 * @param   bool|int  $tzAware  Should the format be timezone aware? See notes above.
	 *
	 * @return string
	 */
	public static function date($date, $format = null, $tzAware = true)
	{
		// Which timezone should I use?
		$tz = null;

		if ($tzAware !== false)
		{
			$userId    = is_bool($tzAware) ? null : (int) $tzAware;

			try
			{
				$tzDefault = Factory::getApplication()->get('offset');
			}
			catch (\Exception $e)
			{
				$tzDefault = new DateTimeZone('GMT');
			}

			$user      = Factory::getUser($userId);
			$tz        = $user->getParam('timezone', $tzDefault);
		}

		$jDate = new Date($date, $tz);

		if (empty($format))
		{
			$format = self::getContainer()->params->get('dateformat', 'Y-m-d H:i T');
			$format = str_replace('%', '', $format);
		}

		return $jDate->format($format, true);
	}

	/**
	 * Returns the current Akeeba Data Compliance container object
	 *
	 * @return  Container
	 */
	protected static function getContainer()
	{
		static $container = null;

		if (is_null($container))
		{
			$container = Container::getInstance('com_datacompliance');
		}

		return $container;
	}

}