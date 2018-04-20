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

class GDPRPrepareATSRemovePrivateInfo extends DataComplianceCliBase
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

		$this->out('Retrieving ticket list for removal..');
		$ticketIDs = $this->getDeadTicketIDs();

		$this->out(sprintf("Found %u tickets to be removed. Go make yourself a coffee.", count($ticketIDs)));

		/** @var \Akeeba\TicketSystem\Admin\Model\Tickets $ticketModel */
		$ticketModel = $this->container->factory->model('Tickets')->tmpInstance();

		foreach ($ticketIDs as $id)
		{
			try
			{
				$ticketModel->findOrFail($id);
			}
			catch (\FOF30\Model\DataModel\Exception\RecordNotLoaded $e)
			{
				$this->out("!!! Cannot load ticket $id !!!");

				continue;
			}

			$fmtId = sprintf('%5u', $id);
			$jDate = $ticketModel->getContainer()->platform->getDate($ticketModel->created_on);
			$fmtDate = $jDate->toRFC822();
			$this->out("#{$fmtId}. [$fmtDate] {$ticketModel->title}");

			$ticketModel->forceDelete();
		}

		parent::execute();
	}

	public function getDeadTicketIDs(): array
	{
		$db    = $this->container->db;
		// Future me: I am so, so sorry.
		$query = <<< SQL
SELECT `ats_ticket_id`
FROM
  (
    -- Unpublished tickets
    SELECT `t`.`ats_ticket_id`
    FROM
      `#__ats_tickets` `t`
    WHERE
      `t`.`enabled` = 0

    UNION

    -- Private tickets of users who have never been subscribers
    SELECT `t`.`ats_ticket_id`
    FROM
      `#__ats_tickets` `t`
    WHERE
      `t`.`public` = 0
      AND `t`.`created_by` NOT IN (SELECT `user_id`
                                   FROM `#__akeebasubs_subscriptions` `s`
                                   WHERE `user_id` != 0
                                   GROUP BY `user_id`)

    UNION

    -- Private tickets by people who are no longer subscribers for at least 6 months
    SELECT `t`.`ats_ticket_id`
    FROM
      `#__ats_tickets` `t`
    WHERE
      `t`.`public` = 0
      AND `t`.`created_by` IN (
        SELECT `user_id`
        FROM
          `#__akeebasubs_subscriptions`
        WHERE
          `state` = 'C'
          AND
          `enabled` = 0
          AND
          `publish_down` <= DATE_SUB(NOW(), INTERVAL 6 MONTH)
          AND
          `user_id` NOT IN (SELECT `user_id`
                            FROM `#__akeebasubs_subscriptions` `s`
                            WHERE `enabled` = 1 AND `publish_down` >= now())
        GROUP BY `user_id`
      )
    UNION

    -- Private tickets with connection information not belonging to users with any active subscriptions or are older than 2 years
    SELECT `t`.`ats_ticket_id`
    FROM
      `#__ats_posts` `p`
      INNER JOIN `#__ats_tickets` `t` ON (`t`.`ats_ticket_id` = `p`.`ats_ticket_id`)
    WHERE
      (
        `content` LIKE '%made this ticket private%'
        OR
        `content_html` LIKE '%made this ticket private%'
      )
      AND `t`.`public` = 0
      AND (
        `t`.`created_on` <= DATE_SUB(NOW(), INTERVAL 2 YEAR)
        OR `t`.`created_by` NOT IN (SELECT `user_id`
                                    FROM `#__akeebasubs_subscriptions` `s`
                                    WHERE `enabled` = 1 AND `publish_down` >= now())
      )

    UNION

    -- Private tickets with suspected connection information not belonging to users with any active subscriptions or are older than 2 years
    SELECT `t`.`ats_ticket_id`
    FROM
      `#__ats_posts` `p`
      INNER JOIN `#__ats_tickets` `t` ON (`t`.`ats_ticket_id` = `p`.`ats_ticket_id`)
    WHERE
      (
        `content` LIKE '%connection information%'
        OR `content` LIKE '%connection details%'
        OR `content` LIKE '%connect to your site%'
        OR `content` LIKE '%business day%'
      )
      AND `t`.`public` = 0
      AND (
        `t`.`created_on` <= DATE_SUB(NOW(), INTERVAL 2 YEAR)
        OR `t`.`created_by` NOT IN (SELECT `user_id`
                                    FROM `#__akeebasubs_subscriptions` `s`
                                    WHERE `enabled` = 1 AND `publish_down` >= now())
      )
  ) `monster`

GROUP BY `ats_ticket_id`
ORDER BY `ats_ticket_id` ASC

SQL;

		return $db->setQuery($query)->loadColumn(0);
	}
}

DataComplianceCliBase::getInstance('GDPRPrepareATSRemovePrivateInfo')->execute();