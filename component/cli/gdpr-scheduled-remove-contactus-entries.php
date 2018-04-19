<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
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

class GDPRScheduleremoveContactUs extends DataComplianceCliBase
{
	public function execute()
	{
		// Delete Contact Us entries over 30 days old
		$threshold = 30;

		$container = \FOF30\Container\Container::getInstance('com_contactus');
		$db        = $container->db;
		$now       = new DateTime();
		$interval  = new DateInterval("P30D");
		$cutoff    = $now->sub($interval);
		$jDate     = $container->platform->getDate($cutoff->getTimestamp());

		$query = $db->getQuery(true)
			->delete($db->qn('#__contactus_items'))
			->where($db->qn('created_on') . ' <= ' . $db->q($jDate->toSql()));

		$this->out("Deleting contact forms older than {$jDate->toRFC822()}");

		$db->setQuery($query)->execute();

		parent::execute();
	}

}

DataComplianceCliBase::getInstance('GDPRScheduleremoveContactUs')->execute();