<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
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
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Log\LogEntry;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Database\DatabaseDriver;
use Joomla\Registry\Registry;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

defined('_JEXEC') or die;

class LifecycleDelete extends AbstractCommand
{
	use ConfigureIO;
	use MemoryInfo;
	use TimeInfo;
	use MVCFactoryAwareTrait;

	/**
	 * The default command name
	 *
	 * @var    string
	 * @since  3.0.0
	 */
	protected static $defaultName = 'datacompliance:lifecycle:delete';

	/**
	 * Configure the command.
	 *
	 * @return  void
	 *
	 * @since   3.0.0
	 */
	protected function configure(): void
	{
		$this->setDescription(Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLEDELETE_DESC'));
		$this->setHelp(Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLEDELETE_HELP'));

		$this->addOption('dry-run', 'r', InputOption::VALUE_NONE, Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLEDELETE_OPT_DRYRUN'));
		$this->addOption('force', 'f', InputOption::VALUE_NONE, Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLEDELETE_OPT_FORCE'));
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
		$force = (bool) $input->getOption('force', false);

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
		$db = Factory::getContainer()->get('DatabaseDriver');
		$db->setMonitor(null);

		$this->ioStyle->section(Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLEDELETE_LBL_HEAD'));

		/** @var WipeModel $wipeModel */
		$wipeModel = $this->mvcFactory->createModel('Wipe', 'Administrator');
		$userIDs   = $wipeModel->getLifecycleUserIDs();

		if (empty($userIDs))
		{
			$this->ioStyle->warning(Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLEDELETE_LBL_NOACTION'));

			return 0;
		}

		$numRecords   = count($userIDs);
		$deleted      = 0;
		$notNotified  = 0;
		$cannotDelete = 0;

		$this->ioStyle->text(Text::plural('COM_DATACOMPLIANCE_CLI_LIFECYCLEDELETE_LBL_FOUNDUSERS', $numRecords));

		if ($dryRun)
		{
			$this->ioStyle->text(Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLEDELETE_LBL_DRYRUN'));
		}

		// Current deletion date, used to confirm that the user has been notified
		$deletionDate = new Date();

		// Loop all users to be deleted
		foreach ($userIDs as $id)
		{
			$freeMemory = $this->getFreeMemory();

			if ($freeMemory < 6316032)
			{
				$this->ioStyle->warning(Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_ERR_OUTOFMEMORY'));

				break;
			}

			// Skip records which cannot be deleted
			if (!$force && !$wipeModel->checkWipeAbility($id, 'lifecycle', $deletionDate))
			{
				$this->ioStyle->text(Text::sprintf('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_LBL_SKIPUSER', $id));

				$cannotNotify++;

				continue;
			}

			if (!$force && !$wipeModel->isUserNotified($id, $deletionDate))
			{
				$this->ioStyle->text(Text::sprintf('COM_DATACOMPLIANCE_CLI_LIFECYCLEDELETE_LBL_NOTNOTIFIED', $id));

				$notNotified++;

				continue;
			}

			$this->ioStyle->write(Text::sprintf('COM_DATACOMPLIANCE_CLI_LIFECYCLEDELETE_LBL_DELETING', $id), false);

			$result = true;

			if (!$dryRun)
			{
				$result = $wipeModel->wipe($id, 'lifecycle');
			}

			if ($result)
			{
				$this->ioStyle->text(Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_LBL_OK'));

				$deleted++;

				continue;
			}

			$error = $wipeModel->getError();

			$this->ioStyle->text(Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_LBL_FAILED'));
			$this->ioStyle->text("\t<error>$error</error>");

			$cannotDelete++;
		}

		$end = microtime(true);
		$timeElapsed = $this->timeago($start, $end, 's', false);

		$this->ioStyle->section(Text::_('COM_DATACOMPLIANCE_CLI_LIFECYCLENOTIFY_LBL_SUMMARY'));

		$this->ioStyle->success([
			Text::sprintf('COM_DATACOMPLIANCE_CLI_LIFECYCLEDELETE_LBL_TOTAL', $numRecords),
			Text::sprintf('COM_DATACOMPLIANCE_CLI_LIFECYCLEDELETE_LBL_DELETED', $deleted),
			Text::sprintf('COM_DATACOMPLIANCE_CLI_LIFECYCLEDELETE_LBL_NOTNOTIFIED', $cannotNotify),
			Text::sprintf('COM_DATACOMPLIANCE_CLI_LIFECYCLEDELETE_LBL_SKIPPED', $notNotified),
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
}