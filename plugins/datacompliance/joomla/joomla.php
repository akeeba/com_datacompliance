<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Data Compliance plugin for Core Joomla! User Data
 */
class plgDatacomplianceJoomla extends Joomla\CMS\Plugin\CMSPlugin
{
	/**
	 * Checks whether a user is safe to be deleted. This plugin prevents deletion on the following conditions:
	 * - The user is a Super User
	 * - The user has backend access
	 *
	 * @param   int  $userID  The user ID we are asked for permission to delete
	 *
	 * @return  void  No return value is expected. Throw exceptions when there is a problem.
	 *
	 * @throws  RuntimeException  The error which prevents us from deleting a user
	 */
	public function onDataComplianceCanDelete($userID)
	{
		// TODO
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
	 * @param   int  $userID  The user ID we are asked to delete
	 *
	 * @return  array
	 */
	public function onDataComplianceDeleteUser($userID): array
	{
		// TODO
	}

	/**
	 * Used for exporting the user information in XML format. The returned data is a SimpleXMLElement document with a
	 * data dump following the structure root > domain > item[...] > column[...].
	 *
	 * This plugin exports the following tables / models:
	 * - #__users
	 * - #__user_notes
	 * - #__user_profiles
	 * - #__user_usergroup_map
	 * - #__user_keys
	 *
	 * @param $userID
	 *
	 * @return SimpleXMLElement
	 */
	public function onDataComplianceExportUser($userID): SimpleXMLElement
	{
		// TODO
	}
}