<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 *
 * This is a preparatory CLI script. It removes the posts from all of the categories which we have already unpublished
 * from our site.
 *
 * It removes the tickets, posts and attachments. It does not remove the categories themselves since Joomla! does not
 * let us do that from CLI (it tries to access the Joomla! session which is nonexistent under the CLI).
 */

define('_JEXEC', 1);

// Load system defines
if (file_exists(__DIR__ . '/defines.php'))
{
	include_once __DIR__ . '/defines.php';
}

if (!defined('_JDEFINES'))
{
	$curDir = __DIR__;

	if (!file_exists($curDir . '/includes/defines.php'))
	{
		$curDir = getcwd();
	}

}

if (!defined('_JDEFINES'))
{
	$path = rtrim($curDir, DIRECTORY_SEPARATOR);
	$rpos = strrpos($path, DIRECTORY_SEPARATOR);
	$path = substr($path, 0, $rpos);
	define('JPATH_BASE', $path);
	require_once JPATH_BASE . '/includes/defines.php';
}

// Load the rest of the framework include files
require_once JPATH_LIBRARIES . '/import.legacy.php';
require_once JPATH_LIBRARIES . '/cms.php';

// Load the JApplicationCli class
JLoader::import('joomla.application.cli');
JLoader::import('joomla.environment.request');
JLoader::import('joomla.environment.uri');
JLoader::import('joomla.utilities.date');
JLoader::import('joomla.application.component.helper');
JLoader::import('legacy.component.helper');

// Joomla! 3.5 and later does not load the configuration.php unless you explicitly tell it to.
JFactory::getConfig(JPATH_CONFIGURATION . '/configuration.php');

class GDPRPrepareATSRemoveDeadCats extends JApplicationCli
{
	/**
	 * @var \FOF30\Container\Container
	 */
	private $container;

	public function execute()
	{
		if (!defined('FOF30_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof30/include.php'))
		{
			$this->out("FOF 3.0 is not installed");

			exit(255);
		}

		$this->container = \FOF30\Container\Container::getInstance('com_ats', [], 'admin');

		$this->out('Retrieving dead categories...');
		$deadCats = $this->getDeadCategories();

		foreach ($deadCats as $cat)
		{
			// Get info about the category
			$numTickets    = $this->getNumTickets($cat->id);
			$fmtNumTickets = sprintf('%5u', $numTickets);
			$fmtCatId      = sprintf('%5u', $cat->id);
			$prefixLevel   = str_repeat('  ', ($cat->level - 1));

			$this->out("#{$fmtCatId}. [$fmtNumTickets] {$prefixLevel}{$cat->title}");

			// Delete its tickets
			/** @var \Akeeba\TicketSystem\Admin\Model\Tickets $ticketModel */
			$ticketModel = $this->container->factory->model('Tickets')->tmpInstance();
			$ticketModel->catid($cat->id);
			$generator = $ticketModel->getGenerator(0, 0, true);

			/** @var \Akeeba\TicketSystem\Admin\Model\Tickets $item */
			foreach ($generator as $item)
			{
				$this->out("        {$prefixLevel}  -- {$item->ats_ticket_id}. {$item->title}");
				$item->forceDelete();
			}

			/**
			 * Delete the category itself SHIT! I CANNOT DO THAT FROM CLI. So after this script is done I have to delete
			 * the actual categories myself.
			 */
		}
	}

	public function getDeadCategories(): array
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true)
		            ->select([$db->qn('id'), $db->qn('level'), $db->qn('title'), $db->qn('alias')])
		            ->from($db->qn('#__categories'))
		            ->where($db->qn('extension') . ' = ' . $db->q('com_ats'))
		            ->where($db->qn('published') . ' != ' . $db->q('1'))
		            ->order($db->qn('lft') . ' ASC')
		;

		return $db->setQuery($query)->loadObjectList();
	}

	public function getNumTickets(int $catId): int
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true)
		            ->select('COUNT(*)')
		            ->from($db->qn('#__ats_tickets'))
		            ->where($db->qn('catid') . ' = ' . $db->q($catId))
		;

		return $db->setQuery($query)->loadResult();
	}
}

$app = JApplicationCli::getInstance('GDPRPrepareATSRemoveDeadCats');

try
{
	$app->execute();
}
catch (Throwable $e)
{
	echo "\n\n\n" . str_repeat('=', 79);
	echo "\nWHOOPSIE!!!!\n" . str_repeat('=', 79) . "\n\n";
	echo $e->getMessage() . "\n\n";
	echo $e->getFile() . ' --- L ' . $e->getLine() . "\n\n";
	echo $e->getTraceAsString();
	echo "\n";
}