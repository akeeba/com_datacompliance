<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Table;

use Akeeba\Component\DataCompliance\Administrator\Mixin\TableAssertionTrait;
use Akeeba\Component\DataCompliance\Administrator\Mixin\TableCreateModifyTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\DispatcherInterface;
use Joomla\Utilities\IpHelper;

defined('_JEXEC') or die;

/**
 * Data export audit trails
 *
 * @property int    datacompliance_usertrail_id   Primary key.
 * @property int    user_id                       User ID whose information changed.
 * @property string created_on                    When the changes were made.
 * @property int    created_by                    Who initiated the changes.
 * @property string requester_ip                  The IP of the person who performed the change.
 * @property array  items                         The changes made. The content of some changes is redacted.
 *
 * @since  3.0.0
 */
class UsertrailsTable extends AbstractTable
{
	use TableCreateModifyTrait
	{
		TableCreateModifyTrait::onBeforeStore as onBeforeStoreCreateModifyAware;
	}
	use TableAssertionTrait;

	public function __construct(DatabaseDriver $db, DispatcherInterface $dispatcher = null)
	{
		$this->_supportNullValue = false;
		$this->setColumnAlias('created', 'created_on');
		$this->setColumnAlias('id', 'datacompliance_usertrail_id');

		parent::__construct('#__datacompliance_usertrails', 'datacompliance_usertrail_id', $db, $dispatcher);

		$this->items = [];
	}

	public function onAfterReset()
	{
		$this->items = [];
	}

	protected function onBeforeStore(&$updateNulls)
	{
		$this->onBeforeStoreCreateModifyAware($updateNulls);

		if (is_array($this->items) || is_object($this->items))
		{
			$this->items = json_encode($this->items);
		}
	}

	protected function onAfterStore(&$result, $updateNulls)
	{
		if (!is_array($this->items))
		{
			$this->items = @json_decode($this->items ?: '{}', true) ?? [];
		}
	}

	protected function onBeforeBind(&$src, &$ignore = [])
	{
		$src = (array)$src;

		if (!is_array($src['items'] ?? ''))
		{
			$this->items = @json_decode($src['params'] ?: '{}', true) ?? [];
		}
	}

	protected function onBeforeCheck()
	{
		if (empty($this->user_id))
		{
			throw new \RuntimeException("Data wipe audit trail: cannot have an empty user ID");
		}

		$this->requester_ip = $this->requester_ip ?: (IpHelper::getIp() ?: '(CLI)');

		if (empty($this->items))
		{
			$this->items = [];
		}
	}


}