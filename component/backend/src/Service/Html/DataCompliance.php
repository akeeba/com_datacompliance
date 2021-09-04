<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Service\Html;

defined('_JEXEC') or die;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\DatabaseAwareTrait;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseDriver;

class DataCompliance
{
	use DatabaseAwareTrait;

	public function __construct(DatabaseDriver $db)
	{
		$this->setDbo($db);
	}

	public function formatDate(?string $date, ?string $format = null, bool $tzAware = true): string
	{
		if (empty($date))
		{
			return '';
		}

		// Which timezone should I use?
		$tz = null;

		if ($tzAware !== false)
		{
			$userId = is_bool($tzAware) ? null : (int) $tzAware;

			try
			{
				$tzDefault = Factory::getApplication()->get('offset');
			}
			catch (\Exception $e)
			{
				$tzDefault = new \DateTimeZone('GMT');
			}

			$user = is_null($userId)
				? Factory::getApplication()->getIdentity()
				: Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);
			$tz   = $user->getParam('timezone', $tzDefault);
		}

		$jDate = new Date($date, $tz);

		return $jDate->format($format ?: 'Y-m-d H:i T', true);
	}
}