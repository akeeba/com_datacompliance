<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Model;

defined('_JEXEC') or die;

use DateInterval;
use Exception;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Statistics for use in the Control Panel page
 *
 * @since  1.0.0
 *
 * @noinspection PhpUnused
 */
#[\AllowDynamicProperties]
class StatsModel extends BaseDatabaseModel
{
	/**
	 * Get the profile deletion statistics for use in graphs
	 *
	 * @param   Date  $from
	 * @param   Date  $to
	 *
	 * @return  array  'user', 'admin' and 'lifecycle' keys
	 * @throws  Exception
	 */
	public function wipeStats(Date $from, Date $to): array
	{
		// Get raw data
		$db      = $this->getDatabase();
		$fromSql = $from->toSql();
		$toSql   = $to->toSql();
		$query   = $db->getQuery(true)
			->select([
				'count(*) AS ' . $db->quoteName('records'),
				$db->quoteName('type'),
				'DATE(' . $db->quoteName('created_on') . ') AS ' . $db->quoteName('date'),
			])
			->from($db->quoteName('#__datacompliance_wipetrails'))
			->where($db->quoteName('created_on') . ' BETWEEN :from AND :to')
			->group([
				$db->quoteName('type'),
				$db->quoteName('date'),
			])
			->bind(':from', $fromSql)
			->bind(':to', $toSql);
		$raw     = $db->setQuery($query)->loadAssocList();

		// Explode into three raw datasets
		$datasets = [
			'user'      => [],
			'admin'     => [],
			'lifecycle' => [],
		];

		foreach ($raw as $entry)
		{
			$datasets[$entry['type']][$entry['date']] = $entry['records'];
		}

		// Normalize datasets
		return [
			'user'      => $this->normalizeDateDataset($datasets['user'], $from, $to),
			'admin'     => $this->normalizeDateDataset($datasets['admin'], $from, $to),
			'lifecycle' => $this->normalizeDateDataset($datasets['lifecycle'], $from, $to),
		];
	}

	/**
	 * Normalize a sparse date-based dataset into one data point per day.
	 *
	 * @param   array  $data  The sparse dataset
	 * @param   Date   $from  Start date
	 * @param   Date   $to    End date
	 *
	 * @return  array
	 * @throws  Exception
	 */
	private function normalizeDateDataset(array $data, Date $from, Date $to): array
	{
		$ret      = [];
		$interval = new DateInterval('P1D');
		$to       = clone Factory::getDate(clone $to);
		$from     = clone Factory::getDate(clone $from);
		$from->setTime(0, 0);
		$to->setTime(0, 0);

		while ($from->toUnix() <= $to->toUnix())
		{
			$thisDate = $from->format('Y-m-d');
			$ret[]    = [
				'x' => $thisDate,
				'y' => $data[$thisDate] ?? 0,
			];
			$from->add($interval);
		}

		return $ret;
	}
}