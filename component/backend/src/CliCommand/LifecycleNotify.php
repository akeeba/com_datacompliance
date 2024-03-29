<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\CliCommand;

use Akeeba\Component\AdminTools\Administrator\CliCommand\MixIt\ConfigureIO;
use Akeeba\Component\AdminTools\Administrator\CliCommand\MixIt\MemoryInfo;
use Akeeba\Component\AdminTools\Administrator\CliCommand\MixIt\TimeInfo;
use Akeeba\Component\DataCompliance\Administrator\Helper\TemplateEmails;
use Akeeba\Component\DataCompliance\Administrator\Model\WipeModel;
use Akeeba\Component\DataCompliance\Site\Model\OptionsModel;
use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Log\LogEntry;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

defined('_JEXEC') or die;

class LifecycleNotify extends AbstractCommand implements DatabaseAwareInterface
{
	use ConfigureIO;
	use MemoryInfo;
	use TimeInfo;
	use MVCFactoryAwareTrait;
	use DatabaseAwareTrait;

	/**
	 * The default command name
	 *
	 * @var    string
	 * @since  3.0.0
	 */
	protected static $defaultName = 'datacompliance:lifecycle:notify';

	/**
	 * Configure the command.
	 *
	 * @return  void
	 *
	 * @since   3.0.0
	 */
	protected function configure(): void
	{
		$this->setDescription(Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_DESC'));
		$this->setHelp(Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_HELP'));

		$this->addOption('period', 'p', InputOption::VALUE_REQUIRED, Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_OPT_PERIOD'));
		$this->addOption('dry-run', 'r', InputOption::VALUE_NONE, Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_OPT_DRYRUN'));
		$this->addOption('debug', 'd', InputOption::VALUE_NONE, Text::_('COM_DATACOMPLIANCE_CLI_COMMON_OPT_DEBUG'));

	}

	/**
	 * @inheritDoc
	 *
	 * @since   3.0.0
	 */
	protected function doExecute(InputInterface $input, OutputInterface $output): int
	{
		$this->configureSymfonyIO($input, $output);

		// Get the options
		$debug  = (bool) $input->getOption('debug', false);
		$dryRun = (bool) $input->getOption('dry-run', false);
		$period = $input->getOption('period', '');

		if (!defined('JDEBUG'))
		{
			define('JDEBUG', $debug);
		}

		if (JDEBUG)
		{
			Log::addLogger([
				// Logger format. "echo" passes the log message verbatim.
				'logger'   => 'callback',
				'callback' => function (LogEntry $entry) {
					$priorities = [
						Log::EMERGENCY => 'EMERGENCY',
						Log::ALERT     => 'ALERT',
						Log::CRITICAL  => 'CRITICAL',
						Log::ERROR     => 'ERROR',
						Log::WARNING   => 'WARNING',
						Log::NOTICE    => 'NOTICE',
						Log::INFO      => 'INFO',
						Log::DEBUG     => 'DEBUG',
					];

					$priority = $priorities[$entry->priority];
					$date     = $entry->date->format(Text::_('DATE_FORMAT_FILTER_DATETIME'));

					$this->ioStyle->writeln(sprintf("[%-9s] %20s -- %s", $priority, $date, $entry->message));
				},

			], Log::ALL, 'com_datacompliance');

			Log::add('Test', Log::DEBUG, 'com_datacompliance');
		}

		// Disable database driver logging to conserve memory
		/** @var DatabaseDriver $db */
		$db = $this->getDatabase();
		$db->setMonitor(null);

		try
		{
			$interval = new DateInterval($period);
		}
		catch (Exception $e)
		{
			$this->ioStyle->error([
				Text::sprintf('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_ERR_INVALIDPERIOD', $period),
			]);


			return 102;
		}

		$when = (clone Factory::getDate())->add($interval);

		$this->ioStyle->section(Text::sprintf('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_LBL_NOTIFYPERIOD', $when->toISO8601()));

		/** @var WipeModel $wipeModel */
		$wipeModel = $this->mvcFactory->createModel('Wipe', 'Administrator');
		$userIDs   = $wipeModel->getLifecycleUserIDs(true, $when);

		if (empty($userIDs))
		{
			$this->ioStyle->warning(Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_LBL_NOUSERS'));

			return 0;
		}

		$numRecords      = count($userIDs);
		$notified        = 0;
		$alreadyNotified = 0;
		$cannotNotify    = 0;

		$this->ioStyle->text(Text::plural('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_LBL_FOUNDUSERS', $numRecords));

		if ($dryRun)
		{
			$this->ioStyle->text(Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_LBL_DRYRUN'));
		}

		foreach ($userIDs as $id)
		{
			$freeMemory = $this->getFreeMemory();

			if ($freeMemory < 6316032)
			{
				$this->ioStyle->warning(Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_ERR_OUTOFMEMORY'));

				break;
			}

			// Skip records which cannot be deleted
			if (!$wipeModel->checkWipeAbility($id, 'lifecycle', $when))
			{
				$this->ioStyle->text(Text::sprintf('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_LBL_SKIPUSER', $id));

				$cannotNotify++;

				continue;
			}

			// Skip records already notified
			if ($wipeModel->isUserNotified($id))
			{
				$this->ioStyle->text(Text::sprintf('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_LBL_ALREADYNOTIFIED', $id));

				$alreadyNotified++;

				continue;
			}

			$this->ioStyle->write(Text::sprintf('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_LBL_NOTIFYING', $id), false);

			$result = true;

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

			if ($result)
			{
				$this->ioStyle->text(Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_LBL_OK'));

				$notified++;

				continue;
			}

			$this->ioStyle->text(Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_LBL_FAILED'));
			$this->ioStyle->text("\t<error>$error</error>");

			$cannotNotify++;
		}

		$this->ioStyle->section(Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_LBL_SUMMARY'));

		$this->ioStyle->success([
			Text::sprintf('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_LBL_TOTAL', $numRecords),
			Text::sprintf('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_LBL_NOTIFIED', $notified),
			Text::sprintf('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_LBL_NOTNOTIFIED', $cannotNotify),
			Text::sprintf('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_LBL_SKIPPED', $alreadyNotified),
		]);

		return 0;
	}

	/**
	 * Get the amount of free PHP memory
	 *
	 * @return  int
	 *
	 * @since   1.0.0
	 */
	private function getFreeMemory(): int
	{
		$memLimit = $this->getMemoryLimit();
		$memUsage = memory_get_usage(true);

		return $memLimit - $memUsage;
	}

	/**
	 * Get the PHP memory limit in bytes
	 *
	 * @return  int  Memory limit in bytes or null if we can't figure it out.
	 *
	 * @since   1.0.0
	 */
	private function getMemoryLimit(): int
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
	 * @return  int  The value in bytes
	 *
	 * @since   1.0.0
	 */
	private function humanToIntegerBytes(string $setting): int
	{
		$val  = trim($setting);
		$last = strtolower(substr($val, -1));

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
	 * Send the email notification to the user
	 *
	 * @param   int       $userID  The user to notify
	 * @param   DateTime  $when    When their account will be deleted
	 *
	 * @return  bool  True if the email sent successfully
	 * @throws  Exception
	 *
	 * @since   1.0.0
	 */
	private function sendEmail(int $userID, DateTime $when): bool
	{
		$user     = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userID);
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

		$deleteDate = clone Factory::getDate($when);
		$format     = Text::_('DATE_FORMAT_LC2') . ' T';
		$deleteDate->setTimezone($tz);

		// Get the actions carried out for the user
		/** @var OptionsModel $optionsModel */
		$optionsModel = $this->mvcFactory->createModel('Options', 'Administrator');
		$actionsList  = $optionsModel->getBulletPoints($user, 'lifecycle');
		$actionsHtml  = "<ul>\n" . implode("\n", array_map(function ($x) {
				return "<li>$x</li>\n";
			}, $actionsList)) . "</ul>";

		$emailVariables =
			[
				'name'          => $user->name,
				'email'         => $user->email,
				'username'      => $user->username,
				'registerdate'  => $user->registerDate,
				'lastvisitdate' => $user->lastvisitDate,
				'requirereset'  => $user->requireReset,
				'resetcount'    => $user->resetCount,
				'lastresettime' => $user->lastResetTime,
				'activation'    => empty($user->activation) ? Text::_('JNO') : $user->activation,
				'block'         => $user->block ? Text::_('JYES') : Text::_('JNO'),
				'id'            => $user->id,
				'actions'       => $actionsHtml,
				'actions_text'  => implode("\n", $actionsList),
				'deletedate'    => $deleteDate->format($format, true),
			];


		Log::add("Emailing the user", Log::DEBUG, 'com_datacompliance');

		try
		{
			return TemplateEmails::sendMail('com_datacompliance.user_warnlifecycle', $emailVariables, $user);
		}
		catch (Exception $e)
		{
			Log::add("Emailing user $userID has failed", Log::ERROR, 'com_datacompliance');
		}

		return false;
	}
}