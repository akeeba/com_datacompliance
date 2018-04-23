<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\DataCompliance\Admin\Helper\Export;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserHelper;

defined('_JEXEC') or die;

/**
 * Data Compliance plugin for Akeeba LoginGuard User Data
 */
class plgDatacomplianceLoginguard extends Joomla\CMS\Plugin\CMSPlugin
{
	protected $container;

	/**
	 * Constructor. Intializes the object:
	 * - Load the plugin's language strings
	 * - Get the com_datacompliance container
	 *
	 * @param   object  $subject  Passed by Joomla
	 * @param   array   $config   Passed by Joomla
	 */
	public function __construct($subject, array $config = array())
	{
		parent::__construct($subject, $config);

		$this->loadLanguage('plg_datacompliance_' . $this->_name);
		$this->container = \FOF30\Container\Container::getInstance('com_datacompliance');
	}

	/**
	 * Performs the necessary actions for deleting a user. Returns an array of the infomration categories and any
	 * applicable IDs which were deleted in the process. This information is stored in the audit log. DO NOT include
	 * any personally identifiable information.
	 *
	 * This plugin takes the following actions:
	 * - The user name is pseudonymized to "user1234" where 1234 is the user ID
	 * - The email is pseudonymized to "user1234@example.com" where 1234 is the user ID
	 * - The password is changed to a long, random string\
	 * - Account creation and last access time are set to dummy values 1/1/1999 and 31/12/1999 GMT.
	 * - User notes are deleted
	 * - User fields are deleted
	 * - User keys (#__user_keys) are deleted
	 * - All user groups are removed from #__user_usergroup_map for this user, making it impossible to login
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


		$db = $this->container->db;

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