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
	 * Performs the necessary actions for deleting a user. Returns an array of the infomration categories and any
	 * applicable IDs which were deleted in the process. This information is stored in the audit log. DO NOT include
	 * any personally identifiable information.
	 *
	 * This plugin takes the following actions:
	 * - Delete the user's LoginGuard TFA method entries
	 *
	 * @param   int     $userID  The user ID we are asked to delete
	 * @param   string  $type    The export type (user, admin, lifecycle)
	 *
	 * @return  array
	 */
	public function onDataComplianceDeleteUser(int $userID, string $type): array
	{
		$ret = [
			'loginguard' => [
				'tfa' => [],
			],
		];


		Log::add("Deleting user #$userID, type ‘{$type}’, LoginGuard data", Log::INFO, 'com_datacompliance');
		Log::add(sprintf('LoginGuard -- RAM %s', $this->memUsage()), Log::INFO, 'com_datacompliance.memory');

		$db = $this->container->db;
		$db->setDebug(false);

		$selectQuery = $db->getQuery(true)
			->select($db->qn('id'))
			->from($db->qn('#__loginguard_tfa'))
			->where($db->qn('user_id') . ' = ' . $db->q($userID));

		$deleteQuery = $db->getQuery(true)
			->delete($db->qn('#__loginguard_tfa'))
			->where($db->qn('user_id') . ' = ' . $db->q($userID));

		try
		{
			$ids                      = $db->setQuery($selectQuery)->loadColumn(0);
			$ids                      = empty($ids) ? [] : implode(',', $ids);
			$ret['loginguard']['tfa'] = $ids;

			$db->setQuery($deleteQuery)->execute();
		}
		catch (Exception $e)
		{
			// No problem if deleting fails.
		}

		return $ret;
	}

	/**
	 * Return a list of human readable actions which will be carried out by this plugin if the user proceeds with wiping
	 * their user account.
	 *
	 * @param   int     $userID  The user ID we are asked to delete
	 * @param   string  $type    The export type (user, admin, lifecycle)
	 *
	 * @return  string[]
	 */
	public function onDataComplianceGetWipeBulletpoints(int $userID, string $type)
	{
		return [
			JText::_('PLG_DATACOMPLIANCE_LOGINGUARD_ACTIONS_1'),
		];
	}

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