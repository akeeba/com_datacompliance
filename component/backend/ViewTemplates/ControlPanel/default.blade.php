<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @var  \Akeeba\DataCompliance\Admin\View\ControlPanel\Html $this For type hinting in the IDE */

// Protect from unauthorized access
defined('_JEXEC') or die;

?>
<div>
	<div class="akeeba-container--50-50">
		<div>
			@include('admin:com_datacompliance/ControlPanel/icons')
		</div>
		<div>
			@include('admin:com_datacompliance/ControlPanel/stats')
		</div>
	</div>
</div>
