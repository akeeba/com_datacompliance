<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Site\Controller\Mixin;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Controller\Mixin\ReusableModels;
use Akeeba\Component\DataCompliance\Administrator\Mixin\TriggerEvent;
use Joomla\CMS\MVC\Controller\BaseController;

trait DisplayAware
{
	use TriggerEvent;
	use ReusableModels;

	/**
	 * Default page caching parameters.
	 *
	 * @var string[]
	 */
	protected static $defaultUrlParams = [
		'limit'            => 'UINT',
		'limitstart'       => 'UINT',
		'filter_order'     => 'CMD',
		'filter_order_Dir' => 'CMD',
		'lang'             => 'CMD',
		'Itemid'           => 'INT',
	];

	/**
	 * Default display method.
	 *
	 * Use onBeforeDisplay and onAfterDisplay to customise this.
	 *
	 * @param   bool   $cachable
	 * @param   array  $urlparams
	 *
	 * @return  BaseController
	 * @throws  \Exception
	 */
	public function display($cachable = true, $urlparams = [])
	{
		$this->triggerEvent('onBeforeDisplay', [&$cachable, &$urlparams]);

		$ret = parent::display($cachable, $urlparams);

		$this->triggerEvent('onAfterDisplay', [&$ret]);

		return $ret;
	}
}