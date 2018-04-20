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

class GDPRScheduleremoveAdmintoolsLog extends DataComplianceCliBase
{
	public function execute()
	{
		// Delete Contact Us entries over 30 days old
		$threshold = 30;

		$container  = \FOF30\Container\Container::getInstance('com_contactus');
		$db         = $container->db;
		$now        = new DateTime();
		$jNow       = $container->platform->getDate($now->getTimestamp());
		$interval   = new DateInterval("P1Y");
		$lastYear   = $now->sub($interval);
		$jLastYear  = $container->platform->getDate($lastYear->getTimestamp());


		$this->out("Admin Tools - Cookies already expired - {$jNow->toRFC822()}");

		$query = $db->getQuery(true)
			->delete($db->qn('#__admintools_cookies'))
			->where($db->qn('valid_to') . ' <= ' . $db->q($jNow->toSql()));

		$db->setQuery($query)->execute();


		$this->out("Admin Tools - IP autoban history older than a year - {$jLastYear->toRFC822()}");

		$query = $db->getQuery(true)
			->delete($db->qn('#__admintools_ipautobanhistory'))
			->where($db->qn('until') . ' <= ' . $db->q($jLastYear->toSql()));

		$db->setQuery($query)->execute();


		$this->out("Admin Tools - Security log older than a year - {$jLastYear->toRFC822()}");

		$query = $db->getQuery(true)
			->delete($db->qn('#__admintools_log'))
			->where($db->qn('logdate') . ' <= ' . $db->q($jLastYear->toSql()));

		$db->setQuery($query)->execute();


		parent::execute();
	}

}

DataComplianceCliBase::getInstance('GDPRScheduleremoveAdmintoolsLog')->execute();