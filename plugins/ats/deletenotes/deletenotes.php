<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

JLoader::import('joomla.plugin.plugin');

/**
 * Deletes the Manager Notes of tickets when they are closed or unpublished.
 */
class plgAtsDeletenotes extends JPlugin
{
	/** @var \FOF30\Container\Container FOF Container */
	private $container;

	/**
	 * Public constructor
	 *
	 * @param   object  $subject  The object to observe
	 * @param   array   $config   Configuration parameters to the plugin
	 */
	public function __construct(&$subject, $config = array())
	{
		parent::__construct($subject, $config);

		if (!defined('FOF30_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof30/include.php'))
		{
			throw new RuntimeException('FOF 3.0 is not installed', 500);
		}

		JLoader::import('joomla.filesystem.file');

		$version_php = JPATH_ADMINISTRATOR . '/components/com_ats/version.php';

		if (!defined('ATS_VERSION') && JFile::exists($version_php))
		{
			require_once $version_php;
		}

		$this->loadLanguage();
		$this->container = FOF30\Container\Container::getInstance('com_ats');
	}

	/**
	 * Delete the Manager Notes after a ticket is unpublished
	 *
	 * Hook into the onAfterUpdate event of ATS' Tickets model. This event is fired automatically by FOF whenever we
	 * successfully save a ticket.
	 *
	 * @param   \Akeeba\TicketSystem\Admin\Model\Tickets  $ticket  The ticket which just got saved
	 */
	public function onComAtsModelTicketsAfterUpdate($ticket)
	{
		// Only trigger when the ticket is unpublised (not enabled) or closed (status = C)
		$mustTrigger = !$ticket->enabled || ($ticket->status = 'C');

		if (!$mustTrigger)
		{
			return;
		}

		$notes = $ticket->manager_notes;

		if (is_null($notes))
		{
			/** @var \Akeeba\TicketSystem\Admin\Model\ManagerNotes $mnModel */
			$mnModel = $ticket->getContainer()->factory->model('ManagerNotes')->tmpInstance();
			$mnModel->ats_ticket_id($ticket->getId());
			$notes = $mnModel->get(true);
		}

		if ($notes->count() < 1)
		{
			return;
		}

		/** @var \Akeeba\TicketSystem\Admin\Model\ManagerNotes $note */
		foreach ($notes as $note)
		{
			try
			{
				$note->delete();
			}
			catch (Exception $e)
			{
				// No sweat, we'll do a traditional delete afterwards
			}
		}

		$db = $ticket->getDbo();
		$query = $db->getQuery(true)
			->delete($db->qn('#__ats_managernotes'))
			->where($db->qn('ats_ticket_id') . ' = ' . $ticket->getId());

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			// Well, not much I can do if the DB has gone tits up, yes?
		}
	}
}
