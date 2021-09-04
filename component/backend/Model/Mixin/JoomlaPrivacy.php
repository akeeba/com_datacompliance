<?php
/*
 * @package   paddle
 * @copyright Copyright (c)2021-2021 Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Model\Mixin;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Component\Privacy\Administrator\Table\RequestTable;

trait JoomlaPrivacy
{
	/**
	 * Get an instance of the Joomla Privacy Request table
	 *
	 * @return null|\PrivacyTableRequest|RequestTable
	 *
	 * @since  2.0.4
	 */
	protected function getJoomlaPrivacyRequestTable()
	{
		// Joomla 3
		if (version_compare(JVERSION, '4.0.0', 'lt'))
		{
			$tableFile = JPATH_ADMINISTRATOR . '/components/com_privacy/tables/request.php';

			// Try to load the PrivacyTableRequest class
			if (!@file_exists($tableFile) && !class_exists('PrivacyTableRequest'))
			{
				return null;
			}

			@include_once $tableFile;

			if (!class_exists('PrivacyTableRequest'))
			{
				return null;
			}

			/** @noinspection PhpIncompatibleReturnTypeInspection */
			return Table::getInstance('Request', 'PrivacyTable');
		}

		// Joomla 4
		if (!class_exists(RequestTable::class))
		{
			return null;
		}

		$db = Factory::getContainer()->get('DatabaseDriver');

		return new RequestTable($db);
	}

}