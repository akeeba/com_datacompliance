<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\View\Mixin;

use Akeeba\Component\DataCompliance\Administrator\Mixin\TriggerEvent;

defined('_JEXEC') || die;

trait TaskBasedEvents
{
	use TriggerEvent;

	public function display($tpl = null)
	{
		$task = $this->getModel()->getState('task');

		$eventName = 'onBefore' . ucfirst($task);
		$this->triggerEvent($eventName, [&$tpl]);

		parent::display($tpl);

		$eventName = 'onAfter' . ucfirst($task);
		$this->triggerEvent($eventName, [&$tpl]);
	}
}