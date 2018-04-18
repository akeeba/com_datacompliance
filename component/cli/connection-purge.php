<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Define ourselves as a parent file
define('_JEXEC', 1);

// Setup and import the base CLI script
$minphp = '5.4.0';
$curdir = __DIR__;

require_once __DIR__ . '/../administrator/components/com_datacompliance/assets/cli/base.php';

class AdmintoolsDbrepair extends ConnectionCliBase
{
	public function flushAssets()
	{
		// This is an empty function since JInstall will try to flush the assets even if we're in CLI (!!!)
		return true;
	}

	public function execute()
	{
		// Load the language files
		$paths	 = array(JPATH_ADMINISTRATOR, JPATH_ROOT);
		$jlang	 = JFactory::getLanguage();
		$jlang->load('com_connection', $paths[0], 'en-GB', true);
		$jlang->load('com_connection', $paths[1], 'en-GB', true);
		$jlang->load('com_connection' . '.override', $paths[0], 'en-GB', true);
		$jlang->load('com_connection' . '.override', $paths[1], 'en-GB', true);

		$debugmessage = '';

		if ($this->input->get('debug', -1, 'int') != -1)
		{
			if (!defined('AKEEBADEBUG'))
			{
				define('AKEEBADEBUG', 1);
			}

			$debugmessage = "*** DEBUG MODE ENABLED ***\n";
			ini_set('display_errors', 1);
		}

		$version		 = AKCONNECTION_VERSION;
		$date			 = AKCONNECTION_DATE;

		$phpversion		 = PHP_VERSION;
		$phpenvironment	 = PHP_SAPI;

		if ($this->input->get('quiet', -1, 'int') == -1)
		{
			$year = gmdate('Y');
			echo <<<ENDBLOCK
Akeeba Data Compliance purge details CLI $version ($date)
Copyright (c) 2018-$year Akeeba Ltd / Nicholas K. Dionysopoulos
-------------------------------------------------------------------------------
Akeeba Data Compliance is Free Software, distributed under the terms of the GNU
General Public License version 3 or, at your option, any later version.
This program comes with ABSOLUTELY NO WARRANTY as per sections 15 & 16 of the
license. See http://www.gnu.org/licenses/gpl-3.0.html for details.
-------------------------------------------------------------------------------
You are using PHP $phpversion ($phpenvironment)
$debugmessage


ENDBLOCK;
		}

		// Attempt to use an infinite time limit, in case you are using the PHP CGI binary instead
		// of the PHP CLI binary. This will not work with Safe Mode, though.
		$safe_mode = true;

		if (function_exists('ini_get'))
		{
			$safe_mode = ini_get('safe_mode');
		}

		if (!$safe_mode && function_exists('set_time_limit'))
		{
			if ($this->input->get('quiet', -1, 'int') == -1)
			{
				echo "Unsetting time limit restrictions.\n";
			}

			@set_time_limit(0);
		}
		elseif (!$safe_mode)
		{
			if ($this->input->get('quiet', -1, 'int') == -1)
			{
				echo "Could not unset time limit restrictions; you may get a timeout error\n";
			}
		}
		else
		{
			if ($this->input->get('quiet', -1, 'int') == -1)
			{
				echo "You are using PHP's Safe Mode; you may get a timeout error\n";
			}
		}

		if ($this->input->get('quiet', -1, 'int') == -1)
		{
			echo "\n";
		}

		// Work around some misconfigured servers which print out notices
		if (function_exists('error_reporting'))
		{
			$oldLevel = error_reporting(0);
		}

		$container = \FOF30\Container\Container::getInstance('com_connection', [], 'admin');

		if (function_exists('error_reporting'))
		{
			error_reporting($oldLevel);
		}

		$this->out("Purge completed.");
	}
}

// Load the version file
require_once JPATH_ADMINISTRATOR . '/components/com_connection/version.php';

// Instanciate and run the application
ConnectionCliBase::getInstance('ConnectionCliBase')->execute();
