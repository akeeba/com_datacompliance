<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Model;

defined('_JEXEC') or die;

use Akeeba\DataCompliance\Admin\Helper\Export as ExportHelper;
use FOF30\Model\Model;
use RuntimeException;
use SimpleXMLElement;

/**
 * A model to wipe users (right to be forgotten)
 */
class Wipe extends Model
{
	protected $error = '';

	/**
	 * Wipes the user information. If it returns FALSE use getError to retrieve the reason.
	 *
	 * @param   int  $userId  The user ID to export
	 *
	 * @return  bool  True on success.
	 *
	 * @throws  RuntimeException  If wipe is not possible
	 */
	public function wipe($userId, string $type = 'user'): bool
	{
		if (!$this->checkWipeAbility($userId))
		{
			return false;
		}

		/** @var Wipetrails $auditRecord */
		$auditRecord = $this->container->factory->model('Wipetrails')->tmpInstance();

		// Do I have an existing data wipe record?
		$auditRecord->load($userId);

		if ($auditRecord->user_id == $userId)
		{
			$isDebug     = defined('JDEBUG') && JDEBUG;
			$isSuperUser = $this->container->platform->getUser()->authorise('core.admin');
			$isCli       = $this->container->platform->isCli();

			if (!($isDebug && ($isCli || $isSuperUser)))
			{
				throw new RuntimeException(\JText::_('COM_DATACOMPLIANCE_WIPE_ERR_TRAILEXISTS'));
			}

			$auditRecord->type = $type;
		}
		else
		{
			$auditRecord->create([
				'user_id' => $userId,
				'type'    => $type,
			]);
		}

		// Actually delete the records
		$platform = $this->container->platform;
		$platform->importPlugin('datacompliance');

		$auditItems = [];
		$results    = $platform->runPlugins('onDataComplianceDeleteUser', [$userId, $type]);

		foreach ($results as $result)
		{
			if (!is_array($result))
			{
				continue;
			}

			$auditItems = array_merge($auditItems, $result);
		}

		// Update audit record with $auditItems
		$auditRecord->load($userId);
		$auditRecord->items = $auditItems;
		$auditRecord->save();

		return true;
	}

	/**
	 * Checks if we can wipe a user. If it returns FALSE use getError to retrieve the reason.
	 *
	 * @param   int  $userId
	 *
	 * @return  bool  True if we can wipe the user
	 */
	public function checkWipeAbility($userId): bool
	{
		$platform = $this->container->platform;
		$platform->importPlugin('datacompliance');

		try
		{
			$platform->runPlugins('onDataComplianceCanDelete', [$userId]);
		}
		catch (RuntimeException $e)
		{
			$this->error = $e->getMessage();

			return false;
		}

		return true;
	}

	/**
	 * Get the reason why wiping is not allowed
	 *
	 * @return  string
	 */
	public function getError(): string
	{
		return $this->error;
	}
}