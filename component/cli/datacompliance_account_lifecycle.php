<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Log\Log;
use Joomla\CMS\Log\LogEntry;

// Setup and import the base CLI script
$minphp = '7.2.0';

// Boilerplate -- START
define('_JEXEC', 1);

foreach ([__DIR__, getcwd()] as $curdir)
{
	if (file_exists($curdir . '/defines.php'))
	{
		define('JPATH_BASE', realpath($curdir . '/..'));
		require_once $curdir . '/defines.php';

		break;
	}

	if (file_exists($curdir . '/../includes/defines.php'))
	{
		define('JPATH_BASE', realpath($curdir . '/..'));
		require_once $curdir . '/../includes/defines.php';

		break;
	}
}

defined('JPATH_LIBRARIES') || die ('This script must be placed in or run from the cli folder of your site.');

require_once JPATH_LIBRARIES . '/fof40/Cli/Application.php';
// Boilerplate -- END

class DataComplianceLifecycleAutomation extends FOFApplicationCLI
{
	public function execute()
	{
		// Enable debug mode?
		$debug = $this->input->getBool('debug', false);
		// Enable memory consumption debug mode?
		$memDebug = $this->input->getBool('memdebug', false);
		// Force deletion of non-notified users (this is useful for the first run on a site with tons of legacy data)?
		$force = $this->input->getBool('force', false);

		if (!defined('JDEBUG'))
		{
			define('JDEBUG', $debug);
		}

		// When debug mode is enabled attach a custom console logger.
		if (JDEBUG)
		{
			Log::addLogger([
				// Logger format. "echo" passes the log message verbatim.
				'logger'   => 'callback',
				'callback' => function (LogEntry $entry) {
					$priorities = array(
						Log::EMERGENCY => 'EMERGENCY',
						Log::ALERT     => 'ALERT',
						Log::CRITICAL  => 'CRITICAL',
						Log::ERROR     => 'ERROR',
						Log::WARNING   => 'WARNING',
						Log::NOTICE    => 'NOTICE',
						Log::INFO      => 'INFO',
						Log::DEBUG     => 'DEBUG',
					);

					$priority = $priorities[$entry->priority];
					$date     = $entry->date->format(JText::_('DATE_FORMAT_FILTER_DATETIME'));

					$this->out(sprintf("[%-9s] %20s -- %s", $priority, $date, $entry->message));
				},

			], Log::ALL, 'com_datacompliance');
		}

		if ($memDebug)
		{
			Log::addLogger([
				// Logger format. "echo" passes the log message verbatim.
				'logger'   => 'echo',
			], Log::ALL, 'com_datacompliance.memory');
		}

		JFactory::getDbo()->setDebug(false);

		$container = \FOF40\Container\Container::getInstance('com_datacompliance', [], 'admin');

		// Load the translations for this component;
		$container->platform->loadTranslations($container->componentName);

		// Load the version information
		include_once $container->backEndPath . '/version.php';

		$version = DATACOMPLIANCE_VERSION;
		$year    = gmdate('Y');

		$this->out("Akeeba Data Compliance $version");
		$this->out("Copyright (c) 2018-$year Akeeba Ltd / Nicholas K. Dionysopoulos");
		$this->out(<<< TEXT
-------------------------------------------------------------------------------
Akeeba Data Compliance is Free Software, distributed under the terms of the GNU
General Public License version 3 or, at your option, any later version.
This program comes with ABSOLUTELY NO WARRANTY as per sections 15 & 16 of the
license. See http://www.gnu.org/licenses/gpl-3.0.html for details.
-------------------------------------------------------------------------------

TEXT
		);

		$start = microtime(true);

		/** @var \Akeeba\DataCompliance\Admin\Model\Wipe $wipeModel */
		$wipeModel = $container->factory->model('Wipe')->tmpInstance();
		$userIDs   = $wipeModel->getLifecycleUserIDs();

		if (empty($userIDs))
		{
			$this->out("No end of life user records were found.");

			return;
		}

		$numRecords   = count($userIDs);
		$deleted      = 0;
		$notNotified  = 0;
		$cannotDelete = 0;

		$this->out("Found $numRecords user record(s) to remove.");

		// Should I confirm each deletion?
		$confirm = $this->input->getBool('confirm', true);
		// Dry run?
		$dryRun = $this->input->getBool('dry-run', false);

		if ($dryRun)
		{
			$this->out("!!! DRY RUN !!! -- NOTHING IS ACTUALLY DELETED");
		}

		if ($confirm)
		{
			if (!$dryRun)
			{
				$this->out("[ ! ] WARNING! CONTINUING WILL DELETE USER ACCOUNTS FOR REAL!");
			}

			$this->out("(i) To prevent this prompt in the future use --confirm=0");
			$this->out("Proceed with lifecycle deletion? [Y/n]");

			$answer = $this->in();
			$answer = substr(strtoupper($answer), 0, 1);

			if (empty($answer) || (strtoupper(substr(trim($answer), 0, 1)) != 'Y'))
			{
				$this->out("\tABORTING ON OPERATOR'S REQUEST.");

				$this->close();
			}
		}

		// Current deletion date, used to confirm that the user has been notified
		$deletionDate = $container->platform->getDate();

		// Loop all users to be deleted
		foreach ($userIDs as $id)
		{
			$freeMemory = $this->getFreeMemory();

			if ($freeMemory < 6316032)
			{
				$this->out('WARNING! Free memory too low (under 6M). Stopping now to prevent a PHP Fatal Error.');

				break;
			}

			// Skip records which cannot be deleted
			if (!$force && !$wipeModel->checkWipeAbility($id, 'lifecycle', $deletionDate))
			{
				$this->out("[!] User $id can not be deleted, skipping.");

				$cannotDelete++;

				continue;
			}

			if (!$force && !$wipeModel->isUserNotified($id, $deletionDate))
			{
				$this->out("[!] User $id has not been notified, skipping.");

				$notNotified++;

				continue;
			}

			if ($confirm)
			{
				$user = \Joomla\CMS\Factory::getUser($id);
				$this->out(sprintf("Do you want to delete user “%s” (%s <%s>) [y/N]?", $user->username, $user->name, $user->email));

				$answer = $this->in();
				$answer = substr(strtoupper($answer), 0, 1);

				if (empty($answer) || (strtoupper(substr(trim($answer), 0, 1)) != 'Y'))
				{
					$this->out("\tSkipping user on operator's request.");
					$cannotDelete++;

					continue;
				}
			}

			$this->out("Removing user $id ");

			if ($dryRun)
			{
				$result = true;
			}
			else
			{
				$result = $wipeModel->wipe($id, 'lifecycle');
			}

			/**
			 * Every time we use JFactory::getUser the User class is storing the user object in memory. We have to
			 * uncache it to prevent running out of memory.
			 */
			$this->uncacheUser($id);

			if ($result)
			{
				$deleted++;

				if ($memDebug)
				{
					$end = microtime(true);
					$timeElapsed = $this->timeago($start, $end, 's', false);
					JLog::add(sprintf('TIME %10s -- RAM %s', $timeElapsed, $this->memUsage()), Log::INFO, 'com_datacompliance.memory');
				}

				continue;
			}

			$error = $wipeModel->getError();
			$this->out('[FAILED]');
			$this->out("\t$error");

			$cannotDelete++;
		}

		$end = microtime(true);
		$timeElapsed = $this->timeago($start, $end, 's', false);

		$this->out("");
		$this->out("SUMMARY");
		$this->out(str_repeat('-', 79));
		$this->out(sprintf('Elapsed time:           %s', $timeElapsed));
		$this->out(sprintf('Maximum memory usage    %s', $this->peakMemUsage()));
		$this->out(sprintf('Total records found:    %u', $numRecords));
		$this->out(sprintf('Deleted:                %u', $deleted));
		$this->out(sprintf('Failed to delete:       %u', $cannotDelete));
		$this->out(sprintf('Skipped (not notified): %u', $notNotified));

		parent::execute();
	}

	private function uncacheUser($id)
	{
		static $reflectionProperty = null;

		if (is_null($reflectionProperty))
		{
			$user = JFactory::getUser();
			$reflectionClass = new ReflectionClass(get_class($user));
			$reflectionProperty = $reflectionClass->getProperty('instances');
			$reflectionProperty->setAccessible(true);
		}

		$instances = $reflectionProperty->getValue(null);

		if (!isset($instances[$id]))
		{
			unset($instances);

			return;
		}

		unset($instances[$id]);
		$reflectionProperty->setValue(null, $instances);
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

			if ($memLimit <= 0)
			{
				$memLimit = 128 * 1024 * 1024;
			}
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
}

FOFApplicationCLI::getInstance('DataComplianceLifecycleAutomation')->execute();