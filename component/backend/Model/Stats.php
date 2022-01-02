<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Model;

defined('_JEXEC') or die;

use Exception;
use FOF40\Date\Date as FOFDate;
use FOF40\Model\Model;
use JDate;
use Joomla\CMS\Date\Date;

/**
 * Statistics for use in the Control Panel page
 */
class Stats extends Model
{
	/**
	 * Get the profile deletion statistics for use in graphs
	 *
	 * @param   JDate|Date|FOFDate $from
	 * @param   JDate|Date|FOFDate $to
	 *
	 * @return  array  'user', 'admin' and 'lifecycle' keys
	 * @throws  Exception
	 */
	public function wipeStats($from, $to): array
	{
		// Get raw data
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->select([
				'count(*) AS ' . $db->qn('records'),
				$db->qn('type'),
				'DATE(' . $db->qn('created_on') . ') AS ' . $db->qn('date'),
			])
			->from($db->qn('#__datacompliance_wipetrails'))
			->where($db->qn('created_on') . ' BETWEEN ' . $db->q($from->toSql()) . ' AND ' . $db->q($to->toSql()))
			->group([
				$db->qn('type'),
				$db->qn('date'),
			]);
		$raw   = $db->setQuery($query)->loadAssocList();

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
	 * @param   array               $data  The sparse dataset
	 * @param   JDate|Date|FOFDate  $from  Start date
	 * @param   JDate|Date|FOFDate  $to    End date
	 *
	 * @return  array
	 * @throws  Exception
	 */
	private function normalizeDateDataset($data, $from, $to)
	{
		$ret      = [];
		$interval = new \DateInterval('P1D');
		$to       = $this->container->platform->getDate(clone $to);
		$from     = $this->container->platform->getDate(clone $from);
		$from->setTime(0, 0, 0);
		$to->setTime(0, 0, 0);

		while ($from->toUnix() <= $to->toUnix())
		{
			$thisDate       = $from->format('Y-m-d');
			$ret[] = [
				'x' => $thisDate,
				'y' => isset($data[$thisDate]) ? $data[$thisDate] : 0
			];
			$from->add($interval);
		}

		return $ret;
	}
}