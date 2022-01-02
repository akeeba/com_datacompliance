<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Table;

use Akeeba\Component\DataCompliance\Administrator\Mixin\AssertionAware;
use Akeeba\Component\DataCompliance\Administrator\Table\Mixin\CreateModifyAware;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\DispatcherInterface;
use Joomla\Utilities\IpHelper;

defined('_JEXEC') or die;

/**
 * Data export audit trails
 *
 * @property int    $datacompliance_exporttrail_id Unique ID
 * @property int    $user_id                       User ID whose data was exported
 * @property string $created_on                    When was the exported created on
 * @property int    $created_by                    User ID who created the export
 * @property string $requester_ip                  IP address which requested the export, or "(CLI)".
 *
 * @since  3.0.0
 */
class ExporttrailsTable extends AbstractTable
{
	use CreateModifyAware;
	use AssertionAware;

	public function __construct(DatabaseDriver $db, DispatcherInterface $dispatcher = null)
	{
		$this->_supportNullValue = false;
		$this->setColumnAlias('created', 'created_on');
		$this->setColumnAlias('id', 'datacompliance_exporttrail_id');

		parent::__construct('#__datacompliance_exporttrails', 'datacompliance_exporttrail_id', $db, $dispatcher);
	}

	protected function onBeforeCheck()
	{
		if (empty($this->user_id))
		{
			throw new \RuntimeException("Export audit trail: cannot have an empty user ID");
		}

		$this->requester_ip = $this->requester_ip ?: (IpHelper::getIp() ?: '(CLI)');
	}
}