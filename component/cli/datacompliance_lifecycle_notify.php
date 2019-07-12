<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use FOF30\Container\Container;
use FOF30\Date\Date;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Log\LogEntry;
use Joomla\Registry\Registry;

define('_JEXEC', 1);

// Setup and import the base CLI script
$curdir = __DIR__;
$path   = __DIR__ . '/../administrator/components/com_datacompliance/assets/cli/base.php';

if (file_exists($path))
{
	require_once $path;
}
else
{
	$curDir = getcwd();
	require_once $curDir . '/../administrator/components/com_datacompliance/assets/cli/base.php';
}

/**
 * Send emails to the users whose accounts are going to be removed by the lifecycle policies.
 */
class DataComplianceLifecycleNotify extends DataComplianceCliBase
{
	/**
	 * The component container
	 *
	 * @var  Container
	 */
	protected $container;

	public function execute()
	{
		// Enable debug mode?
		$debug = $this->input->getBool('debug', false);
		// Notify accounts which will be deleted when exactly?
		$period = $this->input->getString('period', null);

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

		// Disable the database driver's debug mode (logging of all queries)
		JFactory::getDbo()->setDebug(false);

		$this->container = Container::getInstance('com_datacompliance', [], 'site');

		// Load the translations for this component;
		$this->container->platform->loadTranslations($this->container->componentName);

		// Load the version information
		include_once $this->container->backEndPath . '/version.php';

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

		if (empty($period))
		{
			echo <<< END

You must specify the period for which lifecycle will be evaluated. For example,
to notify users whose account is going to be deleted next month use:

--period=P1M

The syntax follows PHP's DateInterval specification.

END;

			$this->close(101);
		}

		$start = microtime(true);

		try
		{
			$interval = new DateInterval($period);
		}
		catch (Exception $e)
		{
			echo <<< END

Invalid period specification “{$period}”.

You must specify the period for which lifecycle will be evaluated. For example,
to notify users whose account is going to be deleted next month use:

--period=P1M

The syntax follows PHP's DateInterval specification.

END;

			$this->close(102);
		}

		$when = $this->container->platform->getDate();
		$when->add($interval);

		$this->out(<<< END
Notifying users to be deleted by {$when->toISO8601()}

END
		);

		/** @var \Akeeba\DataCompliance\Admin\Model\Wipe $wipeModel */
		$wipeModel = $this->container->factory->model('Wipe')->tmpInstance();
		$userIDs   = $wipeModel->getLifecycleUserIDs(true, $when);

		if (empty($userIDs))
		{
			$this->out("No end of life user records were found.");

			return;
		}

		$numRecords      = count($userIDs);
		$notified        = 0;
		$alreadyNotified = 0;
		$cannotNotify    = 0;

		$this->out("Found $numRecords user record(s) to notify.");

		// Should I confirm each notiication?
		$confirm = $this->input->getBool('confirm', true);
		// Dry run?
		$dryRun = $this->input->getBool('dry-run', false);

		if ($dryRun)
		{
			$this->out("!!! DRY RUN !!! -- NO EMAILS, NO CHANGES IN THE DATABASE");
		}

		if ($confirm)
		{
			if (!$dryRun)
			{
				$this->out("[ ! ] WARNING! CONTINUING WILL NOTIFY USERS FOR REAL!");
			}

			$this->out("(i) To prevent this prompt in the future use --confirm=0");
			$this->out("Proceed with lifecycle notification? [Y/n]");

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

			// Skip records which cannot be deleted
			if (!$wipeModel->checkWipeAbility($id, 'lifecycle', $when))
			{
				$this->out("[!] User $id will not be deleted, skipping.");

				$cannotNotify++;

				continue;
			}

			// Skip records already notified
			if ($wipeModel->isUserNotified($id))
			{
				$this->out("[!] User $id is already notified, skipping.");

				$alreadyNotified++;

				continue;
			}


			if ($confirm)
			{
				$user = \Joomla\CMS\Factory::getUser($id);
				$this->out(sprintf("Do you want to notify user “%s” (%s <%s>) [y/N]?", $user->username, $user->name, $user->email));

				$answer = $this->in();
				$answer = substr(strtoupper($answer), 0, 1);

				if (empty($answer) || (strtoupper(substr(trim($answer), 0, 1)) != 'Y'))
				{
					$this->out("\tSkipping user on operator's request.");
					$cannotNotify++;

					continue;
				}
			}

			$this->out("Notifying user $id... ", false);

			if (!$dryRun)
			{
				// Mark the user notified
				$result = $wipeModel->notifyUser($id, $when);
				$error  = $wipeModel->getError();

				// Send the email
				if ($result && !$this->sendEmail($id, $when))
				{
					// If the email failed, reset the user's notification
					$wipeModel->resetUserNotification($id);
					$error  = "Cannot send email";
					$result = false;
				}
			}
			else
			{
				$result = true;
			}

			/**
			 * Every time we use JFactory::getUser the User class is storing the user object in memory. We have to
			 * uncache it to prevent running out of memory.
			 */
			$this->uncacheUser($id);

			if ($result)
			{
				$this->out('[OK]');

				$notified++;

				continue;
			}

			$this->out('[FAILED]');
			$this->out("\t$error");

			$cannotNotify++;
		}

		$end         = microtime(true);
		$timeElapsed = $this->timeago($start, $end, 's', false);

		$this->out("");
		$this->out("SUMMARY");
		$this->out(str_repeat('-', 79));
		$this->out(sprintf('Elapsed time:               %s', $timeElapsed));
		$this->out(sprintf('Maximum memory usage        %s', $this->peakMemUsage()));
		$this->out(sprintf('Total records found:        %u', $numRecords));
		$this->out(sprintf('Notified:                   %u', $notified));
		$this->out(sprintf('Failed to notify:           %u', $cannotNotify));
		$this->out(sprintf('Skipped (already notified): %u', $alreadyNotified));

		parent::execute();
	}

	private function uncacheUser(int $id)
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
	 * Send the email notification to the user
	 *
	 * @param   int       $userID  The user to notify
	 * @param   DateTime  $when    When their account will be deleted
	 *
	 * @return  bool  True if the email sent successfully
	 */
	private function sendEmail(int $userID, DateTime $when): bool
	{
		$user     = $this->container->platform->getUser($userID);
		$registry = is_object($user->params) ? $user->params : new Registry($user->params);
		$tzString = $registry->get('timezone', 'GMT');

		try
		{
			$tz = new DateTimeZone($tzString);
		}
		catch (Exception $e)
		{
			$tz = new DateTimeZone('GMT');
		}

		$deleteDate = new Date($when);
		$format     = JText::_('DATE_FORMAT_LC2') . ' T';
		$deleteDate->setTimezone($tz);

		// Get the actions carried out for the user
		/** @var \Akeeba\DataCompliance\Site\Model\Options $optionsModel */
		$optionsModel = $this->container->factory->model('Options')->tmpInstance();
		$actionsList  = $optionsModel->getBulletPoints($user, 'lifecycle');
		$actionsHtml  = "<ul>\n";

		foreach ($actionsList as $action)
		{
			$actionsHtml .= "<li>$action</li>";
		}

		$actionsHtml .= "</ul>\n";

		$extras = [
			'[DELETEDATE]' => $deleteDate->format($format, true),
			'[ACTIONS]'    => $actionsHtml,

		];

		Log::add("Emailing the user", Log::DEBUG, 'com_datacompliance');

		try
		{
			$mailer = \Akeeba\DataCompliance\Admin\Helper\Email::getPreloadedMailer('user_warnlifecycle', $userID, $extras);

			if ($mailer !== false)
			{
				$mailer->addRecipient($user->email, $user->name);
				$mailer->Send();
			}

			return true;
		}
		catch (Exception $e)
		{
			Log::add("Emailing user $userID has failed", Log::ERROR, 'com_datacompliance');
		}

		return false;
	}
}

DataComplianceCliBase::getInstance('DataComplianceLifecycleNotify')->execute();