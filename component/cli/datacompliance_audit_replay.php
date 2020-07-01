<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\DataCompliance\Admin\Model\Wipe;
use FOF30\Container\Container;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Log\LogEntry;

// Setup and import the base CLI script
$minphp = '7.1.0';

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

require_once JPATH_LIBRARIES . '/fof30/Cli/Application.php';
// Boilerplate -- END

/**
 * Replays audit trails on current website
 */
class DataComplianceAuditReplay extends FOFApplicationCLI
{
	/**
	 * The component container
	 *
	 * @var  Container
	 */
	protected $container;

	/**
	 * Sometimes Joomla outputs some messages using the enqueueMessage method, which does not exist under CLI, so we have to mock it
	 *
	 * @param $message
	 * @param $type
	 */
	public function enqueueMessage($message, $type)
	{
		$type = strtoupper($type);

		$priorities = [
			'EMERGENCY',
			'ALERT',
			'CRITICAL',
			'ERROR',
			'WARNING',
			'NOTICE',
			'INFO',
			'DEBUG',
		];

		$priority = $priorities[$type] ?? 'NOTICE';

		$date = date(JText::_('DATE_FORMAT_FILTER_DATETIME'));

		$this->out(sprintf("[%-9s] %20s -- %s", $priority, $date, $message));
	}

	public function execute()
	{
		// Enable debug mode?
		$debug = $this->input->getBool('debug', false);
		// Folder containing the audit trails to replay
		$folder = $this->input->getPath('folder', null);

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
					$date     = $entry->date->format(JText::_('DATE_FORMAT_FILTER_DATETIME'));

					$this->out(sprintf("[%-9s] %20s -- %s", $priority, $date, $entry->message));
				},

			], Log::ALL, 'com_datacompliance');
		}

		// Disable the database driver's debug mode (logging of all queries)
		JFactory::getDbo()->setDebug(false);

		$this->container = Container::getInstance('com_datacompliance', [], 'site');

		// Tell the plugins to not activate because we're replaying an audit log
		Container::getInstance('com_datacompliance')->platform->setSessionVar('__audit_replay', 1, 'com_datacompliance');

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

		if (empty($folder) || !is_dir($folder))
		{
			$this->out("You must supply a folder containing all the audit trails you want to replay");

			$this->close(101);
		}

		$start        = microtime(true);

		Log::add("Disabling site mail", Log::INFO, 'com_datacompliance');
		$this->container->platform->getConfig()->set('mailonline', 0);

		$counter      = 0;
		$dir_iterator = new DirectoryIterator($folder);

		/** @var Wipe $wipeModel */
		$wipeModel = $this->container->factory->model('Wipe')->tmpInstance();

		foreach ($dir_iterator as $file)
		{
			if ($file->isDot() || $file->isDir())
			{
				continue;
			}

			$contents = file_get_contents($file->getPathname());
			$data     = json_decode($contents, true);

			if (!$data)
			{
				$this->out(sprintf("Skipping file %s. Contents could not be decoded", $file->getBasename()));
				continue;
			}

			if (!isset($data['user_id']))
			{
				$this->out(sprintf("File %s is miissing required field 'user_id', skipping", $file->getBasename()));
				continue;
			}

			// Clear the state between any run. Basically this is what tmpInstance is doing, without cloning the current object.
			// Since we're going to run several times, let's try to save some memory
			$wipeModel->clearState();
			$wipeModel->skipAuditRecord(true);

			try
			{
				if(!$wipeModel->wipe($data['user_id'], $data['type']))
				{
					$this->out(sprintf("Could not replay audit for user %s. More details:", $data['user_id']));
					$this->out("\t" . $wipeModel->getError());
				}
			}
			catch (Exception $e)
			{
				$this->out(sprintf("An exception occurred while deleting user %s. The raw exception will follow", $data['user_id']));
				$this->out($e->getMessage());

				continue;
			}

			$counter += 1;
		}

		$end         = microtime(true);
		$timeElapsed = $this->timeago($start, $end, 's', false);

		$this->out("");
		$this->out("SUMMARY");
		$this->out(str_repeat('-', 79));
		$this->out(sprintf('Elapsed time:               %s', $timeElapsed));
		$this->out(sprintf('Maximum memory usage        %s', $this->peakMemUsage()));
		$this->out(sprintf("Audit trails replayed:      %s", $counter));

		parent::execute();
	}
}

FOFApplicationCLI::getInstance('DataComplianceAuditReplay')->execute();