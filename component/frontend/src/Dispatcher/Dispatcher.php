<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Site\Dispatcher;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Dispatcher\Dispatcher as AdminDispatcher;

class Dispatcher extends AdminDispatcher
{
	protected $defaultController = 'options';

	private function loadCommonStaticMedia()
	{
		// INTENTIONALLY LEFT BLANK: There are no common media files in the frontend.
	}
}