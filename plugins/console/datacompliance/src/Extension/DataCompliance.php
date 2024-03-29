<?php
/**
 * @package   DataCompliance
 * @copyright Copyright (c)2018-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Console\DataCompliance\Extension;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\CliCommand\AccountDelete;
use Akeeba\Component\DataCompliance\Administrator\CliCommand\LifecycleDelete;
use Akeeba\Component\DataCompliance\Administrator\CliCommand\LifecycleNotify;
use Joomla\Application\ApplicationEvents;
use Joomla\Application\Event\ApplicationEvent;
use Joomla\CMS\Application\ConsoleApplication;
use Joomla\CMS\Factory as JoomlaFactory;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Throwable;

class DataCompliance extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
	use MVCFactoryAwareTrait;
	use DatabaseAwareTrait;

	private static $commands = [
		AccountDelete::class,
		LifecycleNotify::class,
		LifecycleDelete::class,
	];

	/**
	 * Load the language file on instantiation.
	 *
	 * @var    boolean
	 * @since  3.0.0
	 */
	protected $autoloadLanguage = true;

	public function __construct(&$subject, $config, MVCFactory $mvcFactory)
	{
		parent::__construct($subject, $config);

		$this->setMVCFactory($mvcFactory);
	}


	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 *
	 * @since   3.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			ApplicationEvents::BEFORE_EXECUTE => 'registerCLICommands',
		];
	}

	/**
	 * Registers command classes to the CLI application.
	 *
	 * This is an event handled for the ApplicationEvents::BEFORE_EXECUTE event.
	 *
	 * @param   ApplicationEvent  $event  The before_execite application event being handled
	 *
	 * @since        3.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function registerCLICommands(ApplicationEvent $event)
	{
		$app = $event->getApplication();

		if (!$app instanceof ConsoleApplication)
		{
			return;
		}

		// Only register CLI commands if we can boot up the Akeeba Backup component enough to make it usable.
		try
		{
			$this->initialiseComponent($app);
		}
		catch (Throwable $e)
		{
			return;
		}

		foreach (self::$commands as $commandFQN)
		{
			try
			{
				if (!class_exists($commandFQN))
				{
					continue;
				}

				/** @var AbstractCommand $command */
				$command = new $commandFQN();

				if (method_exists($command, 'setMVCFactory'))
				{
					$command->setMVCFactory($this->getMVCFactory());
				}

				if ($command instanceof DatabaseAwareInterface)
				{
					$command->setDatabase($this->getDatabase());
				}

				$command->setApplication($app);

				$app->addCommand($command);
			}
			catch (Throwable $e)
			{
				continue;
			}
		}
	}

	private function initialiseComponent(ConsoleApplication $app): void
	{
		// Load the Admin Tools language files
		$lang = $this->getApplication()->getLanguage();
		$lang->load('com_datacompliance', JPATH_SITE, 'en-GB', true, true);
		$lang->load('com_datacompliance', JPATH_SITE, null, true, false);
		$lang->load('com_datacompliance', JPATH_ADMINISTRATOR, 'en-GB', true, true);
		$lang->load('com_datacompliance', JPATH_ADMINISTRATOR, null, true, false);
	}
}