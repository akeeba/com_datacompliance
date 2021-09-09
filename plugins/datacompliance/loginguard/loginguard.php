<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\DataCompliance\Admin\Helper\Export;
use Joomla\CMS\Log\Log;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserHelper;

defined('_JEXEC') or die;

if (!include_once (JPATH_ADMINISTRATOR . '/components/com_datacompliance/assets/plugin/AbstractPlugin.php'))
{
	return;
}

/**
 * Data Compliance plugin for Akeeba LoginGuard User Data
 */
class plgDatacomplianceLoginguard extends plgDatacomplianceAbstractPlugin
{

	/**
	 * Used for exporting the user information in XML format. The returned data is a SimpleXMLElement document with a
	 * data dump following the structure root > domain > item[...] > column[...].
	 *
	 * This plugin exports the following tables / models:
	 * - #__loginguard_tfa
	 *
	 * @param $userID
	 *
	 * @return SimpleXMLElement
	 */
	public function onDataComplianceExportUser($userID): SimpleXMLElement
	{
		$db   = $this->container->db;

		$export = new SimpleXMLElement("<root></root>");

		// #__loginguard_tfa
		$domainTfa = $export->addChild('domain');
		$domainTfa->addAttribute('name', 'loginguard_tfa');
		$domainTfa->addAttribute('description', 'Akeeba LoginGuard TFA records');

		$query = $db->getQuery(true)
			->select('*')
			->from('#__loginguard_tfa')
			->where($db->qn('user_id') . ' = ' . $db->q($userID));
		$records = $db->setQuery($query)->loadObjectList();

		foreach ($records as $record)
		{
			Export::adoptChild($domainTfa, Export::exportItemFromObject($record));
		}

		return $export;
	}
}