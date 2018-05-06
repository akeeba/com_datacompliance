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

		/** @var \Akeeba\DataCompliance\Admin\Model\Wipe $wipeModel */
		$wipeModel = $container->factory->model('Wipe')->tmpInstance();
		$userIDs   = $wipeModel->getLifecycleUserIDs();

		if (empty($userIDs))
		{
			$this->out("No end of life user records were found.");

			return;
		}

		$numRecords = count($userIDs);
		$this->out("Found $numRecords user record(s) to remove.");

		foreach ($userIDs as $id)
		{
			$this->out("Removing user $id... ", false);

			$result = $wipeModel->wipe($id, 'lifecycle');

			if ($result)
			{
				$this->out('[OK]');

				continue;
			}

			$error = $wipeModel->getError();
			$this->out('[FAILED]');
			$this->out("\t$error");
		}

		parent::execute();
	}

}

DataComplianceCliBase::getInstance('DataComplianceLifecycleAutomation')->execute();