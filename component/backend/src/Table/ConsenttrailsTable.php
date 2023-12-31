<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Table;

use Akeeba\Component\DataCompliance\Administrator\Mixin\TableAssertionTrait;
use Akeeba\Component\DataCompliance\Administrator\Mixin\TableCreateModifyTrait;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Utilities\IpHelper;

defined('_JEXEC') or die;

/**
 * Consent audit trails
 *
 * @property   string $created_on    When the consent was given / revoked
 * @property   int    $created_by    User consenting / revoking their consent
 * @property   string $requester_ip  The IP of the person who requested the export
 * @property   int    $enabled       Was consent given?
 *
 * @since  3.0.0
 */
class ConsenttrailsTable extends AbstractTable
{
	use TableCreateModifyTrait;
	use TableAssertionTrait;

	public function __construct(DatabaseDriver $db, DispatcherInterface $dispatcher = null)
	{
		$this->_supportNullValue = false;
		$this->setColumnAlias('created', 'created_on');
		$this->setColumnAlias('id', 'created_by');

		parent::__construct('#__datacompliance_consenttrails', 'created_by', $db, $dispatcher);
	}

	protected function onBeforeCheck()
	{
		$this->requester_ip = $this->requester_ip ?: (IpHelper::getIp() ?: '(CLI)');
	}

	public function store($updateNulls = false)
	{
		$this->triggerEvent('onBeforeStore', [&$updateNulls]);

		$result = true;

		// Pre-processing by observers
		$event = AbstractEvent::create(
			'onTableBeforeStore',
			[
				'subject'		=> $this,
				'updateNulls'	=> $updateNulls,
				'k'				=> $this->created_by,
			]
		);
		$this->getDispatcher()->dispatch('onTableBeforeStore', $event);

		try
		{
			if (!$this->hasPrimaryKey())
			{
				$result = false;
			}
			else
			{
				$db = $this->getDbo();
				$query = $db->getQuery(true)
					->delete($this->_tbl)
					->where($db->quoteName('created_by') . ' = :created_by')
					->bind(':created_by', $this->created_by, ParameterType::INTEGER);

				try
				{
					$db->setQuery($query)->execute();
					$db->insertObject($this->_tbl, $this, $this->_tbl_keys[0]);
				}
				catch (\Exception $e)
				{
					$this->setError($e->getMessage());
					$result = false;
				}
			}
		}
		catch (\Exception $e)
		{
			$this->setError($e->getMessage());
			$result = false;
		}

		// Post-processing by observers
		$event = AbstractEvent::create(
			'onTableAfterStore',
			[
				'subject'	=> $this,
				'result'	=> &$result,
			]
		);
		$this->getDispatcher()->dispatch('onTableAfterStore', $event);

		$this->triggerEvent('onAfterStore', [&$result, $updateNulls]);

		return $result;
	}


}