<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Log\Log;
use Joomla\CMS\Log\LogEntry;

define('_JEXEC', 1);

$path = __DIR__ . '/../administrator/components/com_datacompliance/assets/cli/base.php';

if (file_exists($path))
{
	require_once $path;
}
else
{
	$curDir = getcwd();
	require_once $curDir . '/../administrator/components/com_datacompliance/assets/cli/base.php';
}

class DataComplianceLifecycleAutomation extends DataComplianceCliBase
{
	public function execute()
	{
		// Enable debug mode?
		$debug = $this->input->getBool('debug', false);
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

			Log::add('Test', Log::DEBUG, 'com_datacompliance');
		}

		$container = \FOF30\Container\Container::getInstance('com_datacompliance', [], 'admin');

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

		foreach ($userIDs as $id)
		{
			$freeMemory = $this->getFreeMemory();

			if ($freeMemory < 6316032)
			{
				$this->out('WARNING! Free memory too low (under 6M). Stopping now to prevent a PHP Fatal Error.');

				break;
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

			$this->out("Removing user $id... ", false);

			if (!$force && !$wipeModel->isUserNotified($id))
			{
				$this->out('[SKIPPING - NOT NOTIFIED]');

				$notNotified++;

				continue;
			}

			if ($dryRun)
			{
				$result = $wipeModel->checkWipeAbility($id, 'lifecycle');
			}
			else
			{
				$result = $wipeModel->wipe($id, 'lifecycle');
			}

			if ($result)
			{
				$this->out('[OK]');

				$deleted++;

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
}

DataComplianceCliBase::getInstance('DataComplianceLifecycleAutomation')->execute();