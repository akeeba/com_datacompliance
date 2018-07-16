<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

/**
 * You need to set the following variables:
 *
 * $curdir = __DIR__; // Path to your script file
 */

// Required by the CMS
define('DS', DIRECTORY_SEPARATOR);

// Load system defines
if (!isset($curdir))
{
	// I assume I'm located in administrator/components/com_datacompliance/assets/cli
	$curdir = __DIR__ . '/../../../../../cli';
	$realPath = @realpath($curdir);

	if ($realPath !== false)
	{
		$curdir = $realPath;
	}
}

if (file_exists($curdir . '/defines.php'))
{
	include_once $curdir . '/defines.php';
}

if (!defined('_JDEFINES'))
{
	if (!isset($altBasePath))
	{
		$altBasePath  = rtrim($curdir, DIRECTORY_SEPARATOR);
		$lastSlashPos = strrpos($altBasePath, DIRECTORY_SEPARATOR);
		$altBasePath  = substr($altBasePath, 0, $lastSlashPos);
	}

	define('JPATH_BASE', $altBasePath);
	require_once JPATH_BASE . '/includes/defines.php';
}

// Load the framework include files
require_once JPATH_LIBRARIES . '/import.legacy.php';

// Load the CMS import file (newer Joomla! 3 versions)
$cmsImportPath = JPATH_LIBRARIES . '/cms.php';

if (file_exists($cmsImportPath))
{
	@include_once $cmsImportPath;
}

// Load requirements for various versions of Joomla!
JLoader::import('joomla.base.object');
JLoader::import('joomla.application.application');
JLoader::import('joomla.application.applicationexception');
JLoader::import('joomla.log.log');
JLoader::import('joomla.registry.registry');
JLoader::import('joomla.filter.input');
JLoader::import('joomla.filter.filterinput');
JLoader::import('joomla.factory');

// Joomla! 3.5 and later does not load the configuration.php unless you explicitly tell it to.
JFactory::getConfig(JPATH_CONFIGURATION . '/configuration.php');

if (!defined('FOF30_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof30/include.php'))
{
	throw new RuntimeException('FOF 3.0 is not installed', 500);
}

/**
 * Base class for a Joomla! command line application. Adapted from JCli / JApplicationCli
 */
class DataComplianceCliBase
{
	/**
	 * The application input object.
	 *
	 * @var    JInput
	 */
	public $input;

	/**
	 * The application configuration object.
	 *
	 * @var    JRegistry
	 */
	protected $config;

	/**
	 * The application instance.
	 *
	 * @var    DataComplianceCliBase
	 */
	protected static $instance;

	/**
	 * POSIX-style CLI options. Access them with through the getOption method.
	 *
	 * @var   array
	 */
	protected static $cliOptions = array();

	/**
	 * Filter object to use.
	 *
	 * @var    JFilterInput
	 */
	protected $filter = null;

	/**
	 * Class constructor.
	 *
	 * @return  void
	 */
	protected function __construct()
	{
		// Close the application if we are not executed from the command line, Akeeba style (allow for PHP CGI)
		if (array_key_exists('REQUEST_METHOD', $_SERVER))
		{
			die('You are not supposed to access this script from the web. You have to run it from the command line. If you don\'t understand what this means, you must not try to use this file before reading the documentation. Thank you.');
		}

		$cgiMode = false;

		if (!defined('STDOUT') || !defined('STDIN') || !isset($_SERVER['argv']))
		{
			$cgiMode = true;
		}

		// Create a JInput object
		if ($cgiMode)
		{
			$query = "";
			if (!empty($_GET))
			{
				foreach ($_GET as $k => $v)
				{
					$query .= " $k";
					if ($v != "")
					{
						$query .= "=$v";
					}
				}
			}
			$query	 = ltrim($query);
			$argv	 = explode(' ', $query);
			$argc	 = count($argv);

			$_SERVER['argv'] = $argv;
		}

		$this->input = new JInputCLI();

		// Create the registry with a default namespace of config
		$this->config = new JRegistry;

		// Load the configuration object.
		$this->loadConfiguration($this->fetchConfigurationData());

		// Set the execution datetime and timestamp;
		$this->config->set('execution.datetime', gmdate('Y-m-d H:i:s'));
		$this->config->set('execution.timestamp', time());

		// Set the current directory.
		$this->config->set('cwd', getcwd());

		// Create a new JFilterInput
		if (class_exists('JFilterInput'))
		{
			$this->filter = JFilterInput::getInstance();
		}

		// Parse the POSIX options
		$this->parseOptions();
	}

	/**
	 * Returns a reference to the global DataComplianceCliBase object, only creating it if it
	 * doesn't already exist.
	 *
	 * This method must be invoked as: $cli = DataComplianceCliBase::getInstance();
	 *
	 * @param   string $name The name of the DataComplianceCliBase class to instantiate.
	 *
	 * @return  DataComplianceCliBase  A DataComplianceCliBase object
	 */
	public static function &getInstance($name = null)
	{
		// Only create the object if it doesn't exist.
		if (empty(self::$instance))
		{
			if (class_exists($name) && (is_subclass_of($name, 'DataComplianceCliBase')))
			{
				self::$instance = new $name;
			}
			else
			{
				self::$instance = new DataComplianceCliBase;
			}
		}

		return self::$instance;
	}

	/**
	 * Execute the application.
	 *
	 * @return  void
	 */
	public function execute()
	{
		$this->close();
	}

	/**
	 * Exit the application.
	 *
	 * @param   integer $code Exit code.
	 *
	 * @return  void
	 */
	public function close($code = 0)
	{
		exit($code);
	}

	/**
	 * Load an object or array into the application configuration object.
	 *
	 * @param   mixed $data Either an array or object to be loaded into the configuration object.
	 *
	 * @return  void
	 */
	public function loadConfiguration($data)
	{
		// Load the data into the configuration object.
		if (is_array($data))
		{
			$this->config->loadArray($data);
		}
		elseif (is_object($data))
		{
			$this->config->loadObject($data);
		}
	}

	/**
	 * Write a string to standard output.
	 *
	 * @param   string  $text The text to display.
	 * @param   boolean $nl   True to append a new line at the end of the output string.
	 *
	 * @return  void
	 */
	public function out($text = '', $nl = true)
	{
		fwrite(STDOUT, $text . ($nl ? "\n" : null));
	}

	/**
	 * Get a value from standard input.
	 *
	 * @return  string  The input string from standard input.
	 */
	public function in()
	{
		return rtrim(fread(STDIN, 8192), "\n");
	}

	/**
	 * Method to load a PHP configuration class file based on convention and return the instantiated data object.  You
	 * will extend this method in child classes to provide configuration data from whatever data source is relevant
	 * for your specific application.
	 *
	 * @return  mixed  Either an array or object to be loaded into the configuration object.
	 */
	protected function fetchConfigurationData()
	{
		// Set the configuration file name.
		$file = JPATH_BASE . '/configuration.php';

		// Import the configuration file.
		if (!is_file($file))
		{
			return false;
		}
		include_once $file;

		// Instantiate the configuration object.
		if (!class_exists('JConfig'))
		{
			return false;
		}
		$config = new JConfig;

		return $config;
	}

	/**
	 * Returns a fancy formatted time lapse code
	 *
	 * @param   int     $referencedate  Timestamp of the reference date/time
	 * @param   int     $timepointer    Timestamp of the current date/time
	 * @param   string  $measureby	    Time unit. One of s, m, h, d, or y.
	 * @param   bool    $autotext       Add "ago" / "from now" suffix?
	 *
	 * @return  string
	 */
	protected function timeago($referencedate = 0, $timepointer = '', $measureby = '', $autotext = true)
	{
		if ($timepointer == '')
		{
			$timepointer = time();
		}

		// Raw time difference
		$Raw	 = $timepointer - $referencedate;
		$Clean	 = abs($Raw);

		$calcNum = array(
			array('s', 60),
			array('m', 60 * 60),
			array('h', 60 * 60 * 60),
			array('d', 60 * 60 * 60 * 24),
			array('y', 60 * 60 * 60 * 24 * 365)
		);

		$calc = array(
			's'	 => array(1, 'second'),
			'm'	 => array(60, 'minute'),
			'h'	 => array(60 * 60, 'hour'),
			'd'	 => array(60 * 60 * 24, 'day'),
			'y'	 => array(60 * 60 * 24 * 365, 'year')
		);

		if ($measureby == '')
		{
			$usemeasure = 's';

			for ($i = 0; $i < count($calcNum); $i++)
			{
				if ($Clean <= $calcNum[$i][1])
				{
					$usemeasure	 = $calcNum[$i][0];
					$i			 = count($calcNum);
				}
			}
		}
		else
		{
			$usemeasure = $measureby;
		}

		$datedifference = floor($Clean / $calc[$usemeasure][0]);

		if ($autotext == true && ($timepointer == time()))
		{
			if ($Raw < 0)
			{
				$prospect = ' from now';
			}
			else
			{
				$prospect = ' ago';
			}
		}
		else
		{
			$prospect = '';
		}

		if ($referencedate != 0)
		{
			if ($datedifference == 1)
			{
				return $datedifference . ' ' . $calc[$usemeasure][1] . ' ' . $prospect;
			}
			else
			{
				return $datedifference . ' ' . $calc[$usemeasure][1] . 's ' . $prospect;
			}
		}
		else
		{
			return 'No input time referenced.';
		}
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
	 * Returns the peak memory usage, formatted
	 *
	 * @return  string
	 */
	protected function peakMemUsage()
	{
		if (function_exists('memory_get_peak_usage'))
		{
			$size	 = memory_get_peak_usage();
			return $this->formatByteSize($size);
		}
		else
		{
			return "(unknown)";
		}
	}

	/**
	 * Parses POSIX command line options and sets the self::$cliOptions associative array. Each array item contains
	 * a single dimensional array of values. Arguments without a dash are silently ignored.
	 *
	 * This works much better than JInputCli since it allows you to use all POSIX-valid ways of defining CLI parameters.
	 *
	 * @return  void
	 */
	protected function parseOptions()
	{
		global $argc, $argv;

		// Workaround for PHP-CGI
		if (!isset($argc) && !isset($argv))
		{
			$query = "";

			if (!empty($_GET))
			{
				foreach ($_GET as $k => $v)
				{
					$query .= " $k";

					if ($v != "")
					{
						$query .= "=$v";
					}
				}
			}

			$query = ltrim($query);
			$argv  = explode(' ', $query);
			$argc  = count($argv);
		}

		$currentName = "";
		$options     = array();

		for ($i = 1; $i < $argc; $i++)
		{
			$argument = $argv[ $i ];

			$value = $argument;

			if (strpos($argument, "-") === 0)
			{
				$argument = ltrim($argument, '-');

				$name  = $argument;
				$value = null;

				if (strstr($argument, '='))
				{
					list($name, $value) = explode('=', $argument, 2);
				}

				$currentName = $name;

				if (!isset($options[ $currentName ]) || ($options[ $currentName ] == null))
				{
					$options[ $currentName ] = array();
				}
			}

			if ((!is_null($value)) && (!is_null($currentName)))
			{
				$key = null;

				if (strstr($value, '='))
				{
					$parts = explode('=', $value, 2);
					$key   = $parts[0];
					$value = $parts[1];
				}

				$values = $options[ $currentName ];

				if (is_null($values))
				{
					$values = array();
				}

				if (is_null($key))
				{
					array_push($values, $value);
				}
				else
				{
					$values[ $key ] = $value;
				}

				$options[ $currentName ] = $values;
			}
		}

		self::$cliOptions = $options;
	}

	/**
	 * Returns the value of a command line option
	 *
	 * @param   string  $key      The full name of the option, e.g. "foobar"
	 * @param   mixed   $default  The default value to return
	 * @param   string  $type     Joomla! filter type, e.g. cmd, int, bool and so on.
	 *
	 * @return  mixed  The value of the option
	 */
	protected function getOption($key, $default = null, $type = 'raw')
	{
		// If the key doesn't exist set it to the default value
		if (!array_key_exists($key, self::$cliOptions))
		{
			self::$cliOptions[$key] = is_array($default) ? $default : array($default);
		}

		$type = strtolower($type);

		if ($type == 'array')
		{
			return self::$cliOptions[$key];
		}

		return $this->filterVariable(self::$cliOptions[$key][0], $type);
	}

	protected function filterVariable($var, $type = 'cmd')
	{
		// If JFilterInput exists we shall use it
		if (is_object($this->filter))
		{
			return $this->filter->clean($var, $type);
		}

		// Otherwise we'll have to do it THE REALLY HARD WAY, using reflection to call JRequest::_cleanVar.
		$reflector = new ReflectionClass('JRequest');
		$refMethod = $reflector->getMethod('_cleanVar');
		$refMethod->setAccessible(true);
		return $refMethod->invoke(null, $var, 0, $type);
	}

	/**
	 * Get the PHP memory limit in bytes
	 *
	 * @return int  Memory limit in bytes or null if we can't figure it out.
	 */
	protected function getMemoryLimit()
	{
		static $memLimit = null;

		if (is_null($memLimit))
		{
			if (!function_exists('ini_get'))
			{
				$memLimit = 16842752;

				return $memLimit;
			}

			$memLimit = ini_get("memory_limit");
			$memLimit = $this->humanToIntegerBytes($memLimit);
		}


		return $memLimit;
	}

	/**
	 * Converts a human formatted size to integer representation of bytes,
	 * e.g. 1M to 1024768
	 *
	 * @param   string  $setting  The value in human readable format, e.g. "1M"
	 *
	 * @return  integer  The value in bytes
	 */
	protected function humanToIntegerBytes($setting)
	{
		$val = trim($setting);
		$last = strtolower($val{strlen($val) - 1});

		if (is_numeric($last))
		{
			return $setting;
		}

		$val = substr($val, 0, -1);

		switch ($last)
		{
			case 't':
				$val *= 1024;
			case 'g':
				$val *= 1024;
			case 'm':
				$val *= 1024;
			case 'k':
				$val *= 1024;
		}

		return (int) $val;
	}

	/**
	 * Get the amount of free PHP memory
	 *
	 * @return  int
	 */
	protected function getFreeMemory()
	{
		$memLimit = $this->getMemoryLimit();
		$memUsage = memory_get_usage(true);

		return $memLimit - $memUsage;
	}
}

/**
 * @param   Throwable  $ex  The Exception / Error being handled
 */
function akeeba_exception_handler($ex)
{
	echo "\n\n";
	echo "********** ERROR! **********\n\n";
	echo $ex->getMessage();
	echo "\n\nTechnical information:\n\n";
	echo "Code: " . $ex->getCode() . "\n";
	echo "File: " . $ex->getFile() . "\n";
	echo "Line: " . $ex->getLine() . "\n";
	echo "\nStack Trace:\n\n" . $ex->getTraceAsString();
	die("\n\n");
}

/**
 * Timeout handler
 *
 * This function is registered as a shutdown script. If a catchable timeout occurs it will detect it and print a helpful
 * error message instead of just dying cold.
 *
 * @return  void
 */
function akeeba_timeout_handler()
{
	$connection_status = connection_status();

	if ($connection_status == 0)
	{
		// Normal script termination, do not report an error.
		return;
	}

	echo "\n\n";
	echo "********** ERROR! **********\n\n";

	if ($connection_status == 1)
	{
		echo <<< END
The process was aborted on user's request.

This usually means that you pressed CTRL-C to terminate the script (if you're
running it from a terminal / SSH session), or that your host's CRON daemon
aborted the execution of this script.

If you are running this script through a CRON job and saw this message, please
contact your host and request an increase in the timeout limit for CRON jobs.
Moreover you need to ask them to increase the max_execution_time in the
php.ini file or, even better, set it to 0.
END;
	}
	else
	{
		echo <<< END
This script has timed out. As a result, the process has FAILED to complete.

Your host applies a maximum execution time for CRON jobs which is too low for
this script to work properly. Please contact your host and request an increase
in the timeout limit for CRON jobs. Moreover you need to ask them to increase
the max_execution_time in the php.ini file or, even better, set it to 0.
END;


		if (!function_exists('php_ini_loaded_file'))
		{
			echo "\n\n";

			return;
		}

		$ini_location = php_ini_loaded_file();

		echo <<<END
The php.ini file your host will need to modify is located at:
$ini_location
Info for the host: the location above is reported by PHP's php_ini_loaded_file() method.

END;

		die("\n\n");}
}

/**
 * Error handler. It tries to catch fatal errors and report them in a meaningful way. Obviously it only works for
 * cachable fatal errors...
 *
 * @param   int     $errno    Error number
 * @param   string  $errstr   Error string, tells us what went wrong
 * @param   string  $errfile  Full path to file where the error occurred
 * @param   int     $errline  Line number where the error occurred
 *
 * @return  void
 */
function akeeba_error_handler($errno, $errstr, $errfile, $errline)
{
	switch ($errno)
	{
		case E_ERROR:
		case E_USER_ERROR:
			echo "\n\n";
			echo "********** ERROR! **********\n\n";
			echo "PHP Fatal Error: $errstr";
			echo "\n\nTechnical information:\n\n";
			echo "File: " . $errfile . "\n";
			echo "Line: " . $errline . "\n";
			echo "\nStack Trace:\n\n" . debug_backtrace();
			die("\n\n");
			break;

		default:
			break;
	}
}

set_exception_handler('akeeba_exception_handler');
set_error_handler('akeeba_error_handler', E_ERROR | E_USER_ERROR);
register_shutdown_function('akeeba_timeout_handler');
