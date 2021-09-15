<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\CliCommand;

use Akeeba\Component\AdminTools\Administrator\CliCommand\MixIt\ConfigureIO;
use Akeeba\Component\AdminTools\Administrator\CliCommand\MixIt\MemoryInfo;
use Akeeba\Component\AdminTools\Administrator\CliCommand\MixIt\TimeInfo;
use Akeeba\Component\DataCompliance\Administrator\Model\WipeModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Log\LogEntry;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\User\UserHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

defined('_JEXEC') or die;

class AccountDelete extends \Joomla\Console\Command\AbstractCommand
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
	protected static $defaultName = 'datacompliance:account:delete';

	/**
	 * Configure the command.
	 *
	 * @return  void
	 *
	 * @since   3.0.0
	 */
	protected function configure(): void
	{
		$this->setDescription(Text::_('COM_DATACOMPLIANCE_CLI_ACCOUNTDELETE_DESC'));
		$this->setHelp(Text::_('COM_DATACOMPLIANCE_CLI_ACCOUNTDELETE_HELP'));

		$this->addOption('username', 'u', InputOption::VALUE_OPTIONAL, Text::_('COM_DATACOMPLIANCE_CLI_ACCOUNTDELETE_OPT_USERNAME'));
		$this->addOption('id', 'i', InputOption::VALUE_OPTIONAL, Text::_('COM_DATACOMPLIANCE_CLI_ACCOUNTDELETE_OPT_USER_ID'));
		$this->addOption('force', 'f', InputOption::VALUE_NONE, Text::_('COM_DATACOMPLIANCE_CLI_ACCOUNTDELETE_OPT_FORCE'));
		$this->addOption('dry-run', 'd', InputOption::VALUE_NONE, Text::_('COM_DATACOMPLIANCE_CLI_ACCOUNTDELETE_OPT_DRYRUN'));

	}

	/**
	 * @inheritDoc
	 *
	 * @since   3.0.0
	 */
	protected function doExecute(InputInterface $input, OutputInterface $output): int
	{
		// Get the options
		$debug    = (bool) $input->getOption('debug', false);
		$force    = (bool) $input->getOption('force', false);
		$dryRun   = (bool) $input->getOption('dry-run', false);
		$username = $input->getOption('username', '');
		$user_id  = (int) $input->getOption('id', null);

		// Filter the username
		$filter   = new InputFilter();
		$username = empty($username) ? null : $filter->clean($username, 'username');
		$user_id  = $user_id ?: null;

		if (empty($username) && empty($user_id))
		{
			$this->ioStyle->error(Text::_('COM_DATACOMPLIANCE_CLI_ACCOUNTDELETE_ERR_NOUSER'));

			return 255;
		}

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

		$user_id = $user_id ?: UserHelper::getUserId($username);

		if (empty($user_id))
		{
			$this->ioStyle->error(Text::_('COM_DATACOMPLIANCE_CLI_ACCOUNTDELETE_ERR_INVALIDUSER'));

			return 254;
		}

		/** @var User $user */
		$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($user_id);

		if (empty($user->id) || $user->guest)
		{
			$this->ioStyle->error(Text::_('COM_DATACOMPLIANCE_CLI_ACCOUNTDELETE_ERR_INVALIDUSER'));

			return 254;
		}

		$this->ioStyle->info([
			Text::_('COM_DATACOMPLIANCE_CLI_ACCOUNTDELETE_INFO_ABOUT_TO_DELETE'),
			Text::sprintf('COM_DATACOMPLIANCE_CLI_ACCOUNTDELETE_LBL_USERNAME', $user->username),
			Text::sprintf('COM_DATACOMPLIANCE_CLI_ACCOUNTDELETE_LBL_FULLNAME', $user->name),
			Text::sprintf('COM_DATACOMPLIANCE_CLI_ACCOUNTDELETE_LBL_EMAIL', $user->email),
		]);

		/** @var WipeModel $wipeModel */
		$wipeModel = $this->mvcFactory->createModel('Wipe', 'Administrator');

		if ($force)
		{
			$this->ioStyle->warning(Text::_('COM_DATACOMPLIANCE_CLI_ACCOUNTDELETE_WARN_FORCE'));
		}
		elseif (!$wipeModel->checkWipeAbility($user_id, 'admin'))
		{
			$this->ioStyle->error(
				Text::_('COM_DATACOMPLIANCE_CLI_ACCOUNTDELETE_ERR_CANNOTDELETE'),
				$wipeModel->getError()
			);

			return 127;
		}

		if (!$force && $dryRun)
		{
			$this->ioStyle->info(Text::_('COM_DATACOMPLIANCE_CLI_ACCOUNTDELETE_ERR_DRYRUN'));

			return 1;
		}

		if ($wipeModel->wipe($user_id, 'admin', $force))
		{
			$this->ioStyle->success(Text::_('COM_DATACOMPLIANCE_CLI_ACCOUNTDELETE_LBL_SUCCESS'));

			return 0;
		}

		$this->ioStyle->error(
			Text::_('COM_DATACOMPLIANCE_CLI_ACCOUNTDELETE_LBL_FAILED'),
			$wipeModel->getError()
		);

		return 127;
	}
}